<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

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

        $this->updateProfile($this->getBackup());
        $this->respondOrDelete($message, \sprintf(
            'Backup from "%s" restored successfully.',
            \date('j/n H:i', $this->backupTime),
        ));
    }

    #[FiltersAnd(new FilterActive, new FilterCommand('clone'))]
    public function processClone(FromAdminOrOutgoing&Message $message): void
    {
        // TODO: add support for peer arg (e.g. `.clone @test`)
        if (!($repliedTo = $message->getReply()))
            return;

        $this->setBackup($this->fetchProfile('me'));
        $this->updateProfile(
            $this->fetchProfile($repliedTo->senderId)
        );
        $this->respondOrDelete($message, "User profile cloned successfully.");
    }

    public function updateProfile(array $profile): void
    {
        $profile = self::filterProfile($profile);
        if ($profile['birthday']) {
            $this->account->updateBirthday(birthday: $profile['birthday']);
        }
        // if ($profile['photo']) {
        //     $this->account->updateProfile();
        // }
        unset($profile['birthday']/*, $profile['photo']*/);
        $this->account->updateProfile(...\array_filter($profile));
    }

    public function fetchProfile($peer): array
    {
        $info = $this->getFullInfo($peer);
        $user = $info['User'];
        $full = $info['full'];

        unset($user['photo']['personal']);

        return [
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name']  ?? null,
            'about'      => $full['about']      ?? null,
            'birthday'   => $full['birthday']   ?? null,
            'photo'      => $user['photo'],
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

    public function clear(): void
    {
        $this->backup = [];
        $this->backupTime = 0;
    }


    private const FIELDS = ['first_name', 'last_name', 'about', 'birthday', 'photo'];
    private const REQ_FIELDS = ['first_name'];

    public static function filterProfile(array $profile): array
    {
        foreach (\array_keys($profile) as $key) {
            if (!\in_array($key, self::FIELDS, true)) {
                unset($profile[$key]);
            }
        }
        foreach (self::REQ_FIELDS as $key) {
            if (empty($profile[$key])) {
                throw new \InvalidArgumentException(
                    \sprintf("Required profile field '%s' not set", $key)
                );
            }
        }
        return $profile;
    }
}
