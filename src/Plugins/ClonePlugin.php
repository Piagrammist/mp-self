<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\ParseMode;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Fmt;
use Rz\Utils;
use Rz\Enums\GroupedStatus;
use Rz\Filters\FilterActive;
use function Rz\concatLines;
use function Rz\requireArrayKeys;

final class ClonePlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('backup'))]
    public function processBackup(FromAdminOrOutgoing&Message $message): void
    {
        $argOne = \strtolower($message->commandArgs[0] ?? '');

        if (\in_array($argOne, ['c', 'clear'], true)) {
            if (!$this->hasBackup()) {
                $this->respondOrDelete($message, "There was already no backup!");
                return;
            }
            $this->clear();
            $this->respondOrDelete($message, "Backup deleted successfully.");
            return;
        }

        if (\in_array($argOne, ['s', 'show'], true)) {
            if (!$this->hasBackup()) {
                $this->respond($message, "There is no backup to show!");
                return;
            }
            $backup = $this->getBackup();
            $lines  = [
                \sprintf('*Backup of "%s"*', $this->getBackupDate()),
                '',
                $this->prefix(
                    \sprintf("_First name: `%s`_", $backup['first_name']),
                    \sprintf("_Last name: %s_",    Fmt::str  ($backup['last_name'], true)),
                    \sprintf("_Birthday: %s_",     Fmt::birth($backup['birthday'],  true)),
                    \sprintf("_Bio: %s_",          Fmt::str  ($backup['about'],     true)),
                    \sprintf("_Photo: %s_",        Fmt::bool ( !empty($backup['photo']) )),
                ),
            ];
            $message->editText(concatLines(...$lines), ParseMode::MARKDOWN);
            return;
        }

        $this->setBackup($this->fetchProfile('me'));
        $this->respondOrDelete($message, "Profile backed-up successfully.");
    }

    #[FiltersAnd(new FilterActive, new FilterCommand('restore'))]
    public function processRestore(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->hasBackup()) {
            $this->respondError($message, "No backup to restore from!");
            return;
        }

        $date     = $this->getBackupDate();
        $states   = $this->updateProfile($this->getBackup(), $message);
        $response = $this->genUpdateResponse($states, \array_map(
            static fn($text) => \sprintf($text, $date),
            [
                'success' => 'Backup from "%s" restored successfully.',
                'partial' => 'Managed to restore backup from "%s" partially.',
                'fail'    => 'Failed to restore backup from "%s"!',
            ],
        ));
        $this->respondOrDelete($message, $response, false);
    }

    #[FiltersAnd(new FilterActive, new FilterCommand('clone'))]
    public function processClone(FromAdminOrOutgoing&Message $message): void
    {
        if ($peer = $message->commandArgs[0] ?? null) {
            try {
                $id = $this->getId($peer);
            } catch (\Throwable $e) {
                $this->respondError($message, $e);
                return;
            }
        } elseif ($id = $message->getReply()?->senderId) {
        } else {
            $id = $message->chatId;
        }

        if (!$this->hasBackup()) {
            $this->setBackup($this->fetchProfile('me'));
        }

        $states   = $this->updateProfile($this->fetchProfile($id), $message);
        $response = $this->genUpdateResponse($states, [
            'success' => "Profile cloned successfully.",
            'partial' => "Managed to clone profile partially.",
            'fail'    => "Failed to clone profile!",
        ]);
        $this->respondOrDelete($message, $response, false);
    }

    public function updateProfile(array $profile, Message $message): array
    {
        $states = [
            'common'   => null,
            'photo'    => null,
            'birthday' => null,
        ];
        $profile = self::filterProfile($profile);
        if ($profile['birthday']) {
            $states['birthday'] = !$this->catchFlood($message, 'account.updateBirthday',
                fn() => $this->account->updateBirthday(birthday: $profile['birthday']),
            );
        }
        if ($profile['photo']) {
            $states['photo'] = !$this->catchFlood($message, 'photos.updateProfilePhoto',
                fn() => $this->photos->updateProfilePhoto(id: $profile['photo']),
            );
        }
        unset($profile['birthday'], $profile['photo']);
        $states['common'] = !$this->catchFlood($message, 'account.updateProfile',
            fn() => $this->account->updateProfile(...\array_filter($profile)),
        );
        return $states;
    }

    public function fetchProfile($peer): array
    {
        $info = $this->getFullInfo($peer);
        $full = $info['full'];
        $type = $info['type'];

        if (\in_array($type, ['bot', 'user'], true)) {
            $user = $info['User'];
            return [
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name']     ?? null,
                'about'      => $full['about']         ?? null,
                'birthday'   => $full['birthday']      ?? null,
                'photo'      => $full['profile_photo'] ?? null,
            ];
        }

        $chat = $info['Chat'];
        return [
            'first_name' => $chat['title'],
            'last_name'  =>                        null,
            'about'      => $full['about']      ?? null,
            'birthday'   =>                        null,
            'photo'      => $full['chat_photo'] ?? null,
        ];
    }

    private array $backup = [];
    private int $backupTime = 0;

    public function __sleep(): array
    {
        return ['backup', 'backupTime'];
    }

    public function hasBackup(): bool
    {
        return !empty($this->backup);
    }
    public function getBackup(): array
    {
        return $this->backup;
    }
    public function setBackup(array $profile): self
    {
        $this->backupTime = \time();
        $this->backup = self::filterProfile($profile);
        return $this;
    }

    public function getBackupTime(): int
    {
        return $this->backupTime;
    }
    public function getBackupDate(): string
    {
        return \date('n/j H:i', $this->backupTime);
    }

    public function clear(): void
    {
        $this->backup     = [];
        $this->backupTime = 0;
    }


    private const     FIELDS = ['first_name', 'last_name', 'about', 'birthday', 'photo'];
    private const REQ_FIELDS = ['first_name'];

    public static function filterProfile(array $profile): array
    {
        $profile = \array_filter($profile,
            static fn($key) => \in_array($key, self::FIELDS, true),
            ARRAY_FILTER_USE_KEY,
        );
        requireArrayKeys($profile, self::REQ_FIELDS);
        return $profile;
    }

    /**
     * Generate profile update response, based on how many errors faced.
     *
     * @param array<bool> $updateStates
     * @param array<string, string> $titles
     * @throws \InvalidArgumentException
     */
    private function genUpdateResponse(array $updateStates, array $titles): string
    {
        $state = GroupedStatus::fromStates(...$updateStates);

        if (empty($titles[$state->value])) {
            throw new \InvalidArgumentException(
                \sprintf('Title for "%s" status not set', $state->value)
            );
        }
        $title = \sprintf('_%s_', $titles[$state->value]);

        if ($state !== GroupedStatus::PARTIAL)
            return $title;

        return concatLines(...[
            $title,
            '',
            $this->prefix(
                \sprintf("_Common: %s_",   Fmt::bool($updateStates['common'])),
                \sprintf("_Photo: %s_",    Fmt::bool($updateStates['photo'])),
                \sprintf("_Birthday: %s_", Fmt::bool($updateStates['birthday'])),
            ),
        ]);
    }
}
