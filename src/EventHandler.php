<?php declare(strict_types=1);

namespace Rz;

use danog\DialogId\DialogId;
use danog\MadelineProto\Logger;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\CommandType;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use function Amp\now;
use function Amp\File\getSize;

final class EventHandler extends SimpleEventHandler
{
    private const DELAY = 1;

    private bool $active = true;
    private ?string $styleChar = '_';
    private ?string $prefixChar = '❍';
    private static array $allowedStyles = [
        '*'    => 'bold',
        '_'    => 'italic',
        '__'   => 'underline',
        '`'    => 'monospace',
        '~'    => 'strikethrough',
        'none' => 'no',
    ];

    public function getReportPeers(): array
    {
        return [ADMIN];
    }

    public function __sleep(): array
    {
        return ['active', 'styleChar', 'prefixChar'];
    }

    public function style(string $text): string
    {
        $style = $this->styleChar;
        if ($style === null) {
            return $text;
        }
        return concatLines(...\array_map(
            static fn($l) => "$style{$l}$style",
            splitLines($text),
        ));
    }

    public function prefix(string ...$lines): string
    {
        $prefix = $this->prefixChar;
        return concatLines(...(
            $prefix !== null
                ? \array_map(static fn($l) => "$prefix $l", $lines)
                : $lines
        ));
    }

    public static function periodicAction(int|array $data, callable $cb): void
    {
        if (self::DELAY) {
            $cb = \is_array($data)
                ? static function ($item, $i) use ($cb): void {
                    $cb($item, $i);
                    self::sleep(self::DELAY);
                }
                : static function ($i) use ($cb): void {
                    $cb($i);
                    self::sleep(self::DELAY);
                };
        }
        if (\is_array($data)) {
            \array_walk($data, $cb);
            return;
        }
        for ($i = 0; $i < $data; $i++) {
            $cb($i);
        }
    }

    protected function deleteMessages(int $chatId, array $ids, bool $revoke = true): array
    {
        if (DialogId::isSupergroupOrChannel($chatId)) {
            return $this->channels->deleteMessages(channel: $chatId, id: $ids);
        }
        return $this->messages->deleteMessages(revoke: $revoke, id: $ids);
    }

    #[FilterCommand('help')]
    public function cmdHelp(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $styles = escape('`', \implode('|', \array_keys(self::$allowedStyles)));
        $prefixes = /*escape(['`', '_'], */
            \implode(
                '  ',
                \array_map(
                    static fn($e) => "`$e`",
                    \array_column(CommandType::cases(), 'value')
                )
            )/*)*/
        ;
        $help = concatLines(
            '*Robot commands*',

            '',
            '`.bot <on|off>`',
            '_Make the robot active or inactive._',

            '',
            '`.ping`',
            "_Check robot's responding._",

            '',
            '`.x <code>`',
            "_Execute the php code._",

            '',
            '`.cp <?peer> (reply)`',
            "_Copy and send the replied message to any chat. (default peer: Saved Messages)_",

            '',
            '`.info <?peer> [reply]`',
            "_Get info about the chat (+ a user depending on the reply/arg value)._",

            '',
            '`.status`',
            "_Get info about the server & robot's chats._",

            '',
            "`.style <{$styles}>`",
            '_Switch the text styling._',

            '',
            '`.spell <txt>`',
            '_Split text into letters and send them separately._',

            '',
            '`.spam <num_x> <?num_y> <txt>`',
            '_Send x messages, each containing y \* txt. (y can be omitted!)_',

            '',
            '`.del <num_x>`',
            '_Delete x messages from the chat. (0 < x < 100)_',

            // '',
            // '``',
            // '__',

            '',
            \str_repeat('—', 13),
            "*Notes*",
            $this->prefix(
                "_Supported command prefixes are \"{$prefixes}\"_",
                "_`()` means required reply, and `[]`, an optional one._",
            ),
        );
        $message->editText($help, ParseMode::MARKDOWN);
    }

    #[FilterCommand('ping')]
    public function cmdPing(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $message->editText($this->style('Pong!'), ParseMode::MARKDOWN);
    }

    #[FilterCommand('stop')]
    public function cmdStop(FromAdminOrOutgoing&Message $message): void
    {
        $message->editText($this->style('Stopping...'), ParseMode::MARKDOWN);
        $this->stop();
    }

    #[FilterCommand('restart')]
    public function cmdRestart(FromAdminOrOutgoing&Message $message): void
    {
        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $message->editText($this->style('Restart not available on the CLI!'), ParseMode::MARKDOWN);
            return;
        }
        $message->editText($this->style('Restarting...'), ParseMode::MARKDOWN);
        $this->restart();
    }

    #[FilterCommand('bot')]
    public function cmdBotActivation(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $newStatus = \strtolower($message->commandArgs[0]);
        if ($newStatus === 'on') {
            $res = !$this->active
                ? 'Robot is active now!'
                : 'Robot is already active!';
            $this->active = true;
        } elseif ($newStatus === 'off') {
            $res = $this->active
                ? 'Robot is inactive now!'
                : 'Robot is already inactive!';
            $this->active = false;
        }
        if (isset($res)) {
            $message->editText($this->style($res), ParseMode::MARKDOWN);
        }
    }

    #[FilterCommand('x')]
    public function cmdEval(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $code = \mb_substr($message->message, 2);
        $toJSON = fn ($value): string =>
            \json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $tgJSON = fn ($value): string =>
            "```json\n" . $toJSON($value) . "```";
        try {
            \ob_start();
            eval($code);
            $output = \ob_get_clean();
            $output = $output ? "*Result:*\n$output" : $this->style("No output.");
        } catch (\Throwable $e) {
            $output = "*Error:*\n```\n{$e->getMessage()}```";
        } finally {
            $message->reply($output, ParseMode::MARKDOWN);
        }
    }

    #[FilterCommand('cp')]
    public function cmdCopyMessage(FromAdminOrOutgoing&Message $message): void
    {
        $peer = $message->commandArgs[0] ?? 'me';
        $replied = $message->getReply();
        if (!$replied) {
            return;
        }
        $params = [
            'peer'    => $peer,
            'message' => $replied->message,
        ];
        if (!empty($replied->media))    $params['media']    = $replied->media;
        if (!empty($replied->entities)) $params['entities'] = $replied->entities;
        $this->messages->{!empty($replied->media) ? 'sendMedia' : 'sendMessage'}(...$params);
        $message->editText(
            $this->style(\sprintf(
                "Message copy sent to %s.",
                $peer === 'me' ? 'Saved Messages' : $peer,
            )),
            ParseMode::MARKDOWN,
        );
    }

    #[FilterCommand('info')]
    public function cmdInfo(FromAdminOrOutgoing&Message $message): void
    {
        $chat = $this->getInfo($message->chatId);
        $userId = ($replied = $message->getReply())
            ? $replied->senderId
            : $message->commandArgs[0]
                ?? null;
        $empty = '—';
        $username = isset($chat['Chat']['username']) ? "`{$chat['Chat']['username']}`" : $empty;
        $date = \date('j/n/Y', $chat['Chat']['date']);
        $lines = [
            "*Chat*",
            $this->prefix(
                "_Id:_ `{$chat['bot_api_id']}`",
                "_Title:_ `{$chat['Chat']['title']}`",
                "_Username:_ {$username}",
                "_Creation:_ `{$date}`",
                "_Type:_ `{$chat['type']}`",
            ),
        ];
        if ($userId) {
            try {
                $user = $this->getInfo($userId)['User'];
                $name = $user['first_name'] . (
                    isset($user['last_name']) ? " {$user['last_name']}" : ''
                );
                $username = isset($user['username']) ? "`{$user['username']}`" : $empty;
                $lines = [
                    "*User*",
                    $this->prefix(
                        "_Id:_ `{$user['id']}`",
                        "_Name:_ `{$name}`",
                        "_Username:_ {$username}",
                    ),
                    \str_repeat('—', 13),
                    ...$lines,
                ];
            } catch (\Throwable) {
                $message->editText($this->style("[ERROR] Invalid user peer!"), ParseMode::MARKDOWN);
                return;
            }
        }
        $message->editText(concatLines(...$lines), ParseMode::MARKDOWN);
    }

    #[FilterCommand('status')]
    public function cmdStatus(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $message->editText($this->style('Processing...'), ParseMode::MARKDOWN);

        $chats = [
            'bot' => 0,
            'user' => 0,
            'chat' => 0,
            'supergroup' => 0,
            'channel' => 0,
        ];
        foreach ($this->getDialogIds() as $peer) {
            $chats[$this->getInfo($peer)['type']]++;
        }
        $total = \array_sum($chats);

        $mem = readableBytes(\memory_get_usage());
        $peakMem = readableBytes(\memory_get_peak_usage());
        $loggerSettings = $this->getSettings()->getLogger();
        $log = $loggerSettings->getType() === Logger::FILE_LOGGER
            ? readableBytes(getSize($loggerSettings->getExtra()))
            : 'NaN';

        $message->editText(
            concatLines(
                "*Chats*",
                $this->prefix(
                    "_Total: {$total}_",
                    "_Bots: {$chats['bot']}_",
                    "_Users: {$chats['user']}_",
                    "_Groups: {$chats['chat']}_",
                    "_Supergroups: {$chats['supergroup']}_",
                    "_Channels: {$chats['channel']}_"
                ),
                \str_repeat('—', 13),
                "*Server*",
                $this->prefix(
                    "_RAM usage: {$mem}_",
                    "_Peak RAM usage: {$peakMem}_",
                    "_Log volume: {$log}_"
                ),
            ),
            ParseMode::MARKDOWN,
        );
    }

    #[FilterCommand('style')]
    public function cmdStyle(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        if (!isset($message->commandArgs[0]))
            return;

        $newStyle = \strtolower($message->commandArgs[0]);
        if (!\array_key_exists($newStyle, self::$allowedStyles))
            return;

        $this->styleChar = $newStyle === 'none' ? null : $newStyle;
        $message->editText(
            $this->style('Successfully switched to ' . self::$allowedStyles[$newStyle] . ' text styling!'),
            ParseMode::MARKDOWN,
        );
    }

    #[FilterCommand('spell')]
    public function cmdSpell(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        if (!isset($message->commandArgs[0]))
            return;

        $message->delete(true);

        $letters = \mb_str_split(
            \str_replace(
                ' ',
                '-',
                \implode(' ', $message->commandArgs)
            )
        );
        self::periodicAction($letters, static function ($letter) use ($message) {
            $message->sendText($letter);
        });
    }

    #[FilterCommand('spam')]
    public function cmdSpam(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        $args = $message->commandArgs;
        if (!isset($args[1]))
            return;
        if (!\is_numeric($args[0]))
            return;
        if (isset($args[2]) && !\is_numeric($args[1]))
            return;

        $message->delete(true);

        $messageCount = (int)$args[0];
        $txtRepeat = isset($args[2]) ? (int)$args[1] : 1;
        if ($messageCount < 1 || $txtRepeat < 1)
            return;

        $toSend = \str_repeat(
            \trim(\implode(' ', \array_slice($args, isset($args[2]) ? 2 : 1))) . "\n",
            $txtRepeat
        );
        if (!$message->isReply()) {
            self::periodicAction($messageCount, function () use ($message, $toSend) {
                $message->sendText($toSend);
            });
            return;
        }
        $chatId = $message->chatId;
        $replyToMsgId = $message->replyToMsgId;
        self::periodicAction($messageCount, function () use ($chatId, $toSend, $replyToMsgId) {
            $this->sendMessage($chatId, $toSend, replyToMsgId: $replyToMsgId);
        });
    }

    #[FilterCommand('del')]
    public function cmdDelete(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        $count = (int)($message->commandArgs[0] ?? 0);
        if ($count < 1 || $count > 99)
            return;

        $message->editText($this->style('Processing...'), ParseMode::MARKDOWN);

        $response = (function () use ($message, $count): string {
            try {
                $deleted = 0;
                $start = now();

                $messages = $this->messages->getHistory(
                    peer: $message->chatId,
                    limit: $count + 1,
                )['messages'];
                $ids = \array_column($messages, 'id');
                if (($pos = \array_search($message->id, $ids, true)) !== false) {
                    unset($ids[$pos]);
                }
                if (\count($ids) === 0) {
                    return 'No message to delete!';
                }

                $deleted = $this->deleteMessages($message->chatId, $ids)['pts_count'];
                if ($deleted === 0) {
                    return 'Could not delete any message!';
                }

                $end = now();
                $diff = \round($end - $start, 2);

                $tmp = $deleted === $count ? $deleted : "{$deleted}/{$count}";
                return "Successfully deleted {$tmp} messages in {$diff}s!";
            } catch (\Throwable $e) {
                $this->logger("Surfaced while deleting: $e");
                return '[ERROR] Check the logs.';
            }
        })();
        try {
            $message->editText($this->style($response), ParseMode::MARKDOWN);
        } catch (\Throwable) {
        }
    }
}
