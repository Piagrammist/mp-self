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
    private static array $allowedStyles = [
        '*'    => 'bold',
        '_'    => 'italic',
        '__'   => 'underline',
        '`'    => 'monospace',
        '~'    => 'strikethrough',
        'none' => 'no',
    ];


    #region Properties
    private bool $active = true;
    private bool $verbose = true;
    private float $delay = 0.5;
    private ?string $styleChar = '_';
    private ?string $prefixChar = '❍';

    public function __sleep(): array
    {
        return [
            'active',
            'verbose',
            'delay',
            'styleChar',
            'prefixChar',
        ];
    }
    #endregion


    #region Utility Methods
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

    public function periodicAction(int|array $data, callable $cb): void
    {
        if ($delay = $this->delay) {
            $cb = \is_array($data)
                ? static function ($item, $i) use ($cb, $delay): void {
                    $cb($item, $i);
                    self::sleep($delay);
                }
                : static function ($i) use ($cb, $delay): void {
                    $cb($i);
                    self::sleep($delay);
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

    public function fmtError(\Throwable|string $e): string
    {
        return "*Error:*\n```\n$e```";
    }
    #endregion


    #region Tg Methods
    protected function deleteMessages(int $chatId, array $ids, bool $revoke = true): array
    {
        if (DialogId::isSupergroupOrChannel($chatId)) {
            return $this->channels->deleteMessages(channel: $chatId, id: $ids);
        }
        return $this->messages->deleteMessages(revoke: $revoke, id: $ids);
    }

    public function loading(Message $message, ?string $customMessage = null): void
    {
        $message->editText($this->style($customMessage ?: 'Processing...'), ParseMode::MARKDOWN);
    }
    public function loadingOrDelete(Message $message, ?string $customMessage = null): void
    {
        $this->verbose
            ? $this->loading($message, $customMessage)
            : $message->delete();
    }

    public function deleteIfQuiet(Message $message): void
    {
        $this->verbose || $message->delete();
    }

    public function respondIfVerbose(Message $message, string $text): void
    {
        $this->verbose && $message->editText($this->style($text), ParseMode::MARKDOWN);
    }
    public function respondOrDelete(Message $message, string $text): void
    {
        $this->verbose
            ? $message->editText($this->style($text), ParseMode::MARKDOWN)
            : $message->delete();
    }
    public function respondError(Message $message, \Throwable|string $e): void
    {
        $message->reply($this->fmtError($e), ParseMode::MARKDOWN);
    }
    #endregion


    #region Commands
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
            '`.quiet <on|off>`',
            "_Switch the robot's quiet mode._",

            '',
            '`.delay <num_x>`',
            "_Change the periodic actions' delay._",

            '',
            '`.ping`',
            "_Check robot's responding._",

            '',
            '`.x <code>`',
            "_Execute the php code._",

            '',
            '`.cp <?peer> (reply)`',
            "_Copy and send the replied message to any chat. (default peer: current chat)_",

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
            "- _Supported command prefixes are \"{$prefixes}\"_",
            "- _`()` means required reply, and `[]`, an optional one._",
        );
        $message->editText($help, ParseMode::MARKDOWN);
    }

    #[FilterCommand('ping')]
    public function cmdPing(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $this->respondOrDelete($message, 'Pong!');
    }

    #[FilterCommand('stop')]
    public function cmdStop(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $this->respondOrDelete($message, 'Stopping...');
        $this->stop();
    }

    #[FilterCommand('restart')]
    public function cmdRestart(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $this->respondError($message, "Restart not available on the CLI!");
            return;
        }
        $this->respondOrDelete($message, 'Restarting...');
        $this->restart();
    }

    #[FilterCommand('bot')]
    public function cmdBot(FromAdminOrOutgoing&Message $message): void
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

        $this->deleteIfQuiet($message);
        if (isset($res)) {
            $this->respondIfVerbose($message, $res);
        }
    }

    #[FilterCommand('quiet')]
    public function cmdQuiet(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        if (!isset($message->commandArgs[0]))
            return;

        $newStatus = \strtolower($message->commandArgs[0]);
        if ($newStatus === 'on') {
            $res = $this->verbose
                ? 'Robot is quiet now!'
                : 'Robot is already quiet!';
            $this->verbose = false;
        } elseif ($newStatus === 'off') {
            $res = !$this->verbose
                ? 'Robot is verbose now!'
                : 'Robot is already verbose!';
            $this->verbose = true;
        }

        $this->deleteIfQuiet($message);
        if (isset($res)) {
            $this->respondIfVerbose($message, $res);
        }
    }

    #[FilterCommand('delay')]
    public function cmdDelay(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        if (!isset($message->commandArgs[0]))
            return;

        $delay = (int)$message->commandArgs[0];
        if ($delay <= 0) {
            $this->respondError($message, "Delay must be a number greater than zero!");
            return;
        }

        $this->delay = \round($delay / 1e3, 2);
        $this->respondOrDelete($message, "Operation delay changed to {$this->delay}s.");
    }

    #[FilterCommand('x')]
    public function cmdEval(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
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
            $output = $this->fmtError($e);
        } finally {
            $message->reply($output, ParseMode::MARKDOWN);
        }
    }

    #[FilterCommand('cp')]
    public function cmdCopyMessage(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $this->loading($message);

        $peer = $message->commandArgs[0] ?? $message->chatId;
        $replied = $message->getReply();
        if (!$replied) {
            return;
        }
        try {
            $this->getInfo($peer);
        } catch (\Throwable $e) {
            $this->respondError($message, $e);
            return;
        }
        $params = [
            'peer'    => $peer,
            'message' => $replied->message,
        ];
        if (!empty($replied->media))    $params['media']    = $replied->media;
        if (!empty($replied->entities)) $params['entities'] = $replied->entities;
        $this->messages->{!empty($replied->media) ? 'sendMedia' : 'sendMessage'}(...$params);
        $this->respondOrDelete($message, \sprintf(
            "Message copy sent to %s.",
            $peer === 'me' ? 'Saved Messages' : $peer,
        ));
    }

    #[FilterCommand('info')]
    public function cmdInfo(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $this->loading($message);

        $empty = '—';
        $chat = $this->getInfo($message->chatId);
        if (!\in_array($chat['type'], ['user', 'bot'], true)) {
            $userId = ($replied = $message->getReply())
                ? $replied->senderId
                : $message->commandArgs[0]
                    ?? null;
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
        } else {
            $userId = $message->chatId;
        }
        if ($userId) {
            try {
                $user = $this->getInfo($userId)['User'];
                $name = $user['first_name'] . (
                    isset($user['last_name']) ? " {$user['last_name']}" : ''
                );
                $username = isset($user['username']) ? "`{$user['username']}`" : $empty;
                if ($hasChat = isset($lines)) {
                    $prevLines = [
                        \str_repeat('—', 13),
                        ...$lines,
                    ];
                }
                $lines = [
                    "*User*",
                    $this->prefix(
                        "_Id:_ `{$user['id']}`",
                        "_Name:_ `{$name}`",
                        "_Username:_ {$username}",
                    ),
                ];
                if ($hasChat) {
                    $lines = \array_merge($lines, $prevLines);
                }
            } catch (\Throwable $e) {
                $this->respondError($message, $e);
                return;
            }
        }
        assert(\count($lines) > 0);
        $message->editText(concatLines(...$lines), ParseMode::MARKDOWN);
    }

    #[FilterCommand('status')]
    public function cmdStatus(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;

        $this->loading($message);
        $chats = [
            'user' => 0,
            'chat' => 0,
            'supergroup' => 0,
            'channel' => 0,
            'bot' => 0,
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
        $this->respondOrDelete($message, \sprintf(
            "Successfully switched to %s text styling!",
            self::$allowedStyles[$newStyle],
        ));
    }

    #[FilterCommand('spell')]
    public function cmdSpell(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        if (!isset($message->commandArgs[0]))
            return;

        $message->delete(true);
        $letters = \mb_str_split(\str_replace(' ', '-', \implode(' ', $message->commandArgs)));
        $this->periodicAction($letters, static function ($letter) use ($message) {
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
            // Extract text depending if `y` is set or not.
            \trim(\implode(' ', \array_slice($args, isset($args[2]) ? 2 : 1))) . "\n",
            $txtRepeat,
        );
        if (!$message->isReply()) {
            $this->periodicAction($messageCount, function () use ($message, $toSend) {
                $message->sendText($toSend);
            });
            return;
        }
        $chatId = $message->chatId;
        $replyToMsgId = $message->replyToMsgId;
        $this->periodicAction($messageCount, function () use ($chatId, $toSend, $replyToMsgId) {
            $this->sendMessage($chatId, $toSend, replyToMsgId: $replyToMsgId);
        });
    }

    #[FilterCommand('del')]
    public function cmdDelete(FromAdminOrOutgoing&Message $message): void
    {
        if (!$this->active)
            return;
        $argOne = $message->commandArgs[0] ?? null;
        $argTwo = (int)($message->commandArgs[1] ?? 0);
        $serviceOnly = \in_array(\strtolower($argOne), ['s', 'service'], true);
        $count = (int)$argOne;
        if ($serviceOnly) {
            if (!$argTwo)
                return;

            $count = $argTwo;
        } elseif (!$count || ($count < 1 || $count > 99)) {
            return;
        }
        $offsetDate = $message->isReply()
            ? $message->getReply()->date
            : 0;

        $this->loading($message);
        try {
            $response = (function () use ($message, $count, $offsetDate, $serviceOnly): string {
                $deleted = 0;
                $start = now();

                while ($deleted < $count) {
                    $history = $this->messages->getHistory(
                        peer: $message->chatId,
                        offset_date: $offsetDate,
                        limit: $count + 1,
                    )['messages'];
                    if ($serviceOnly) {
                        $history = \array_values(\array_filter($history,
                            static fn($msg) => $msg['_'] === 'messageService'));
                    }
                    // Filter current topic messages.
                    $history = \array_values(\array_filter(
                        \array_map($this->wrapMessage(...), $history),
                        static fn($msg) => $msg->topicId === $message->topicId));

                    $ids = [];
                    foreach ($history as $histMsg) {
                        if ($histMsg->id !== $message->id) {
                            $ids []= $histMsg->id;
                        }
                    }
                    if (\count($ids) === 0)
                        break;

                    // Don't surpass the `count`.
                    if (\count($ids) + $deleted > $count) {
                        $ids = \array_slice($ids, 0, (int)($count - $deleted));
                    }
                    $deleted += $cycle = $this->deleteMessages($message->chatId, $ids)['pts_count'];
                    if ($cycle === 0)
                        break;
                }

                if ($deleted === 0)
                    return "Could not delete any message!";

                $diff = \round(now() - $start, 2);
                return \sprintf("Successfully deleted %s messages in %ss.",
                    $deleted === $count ? $deleted : "{$deleted}/{$count}",
                    $diff,
                );
            })();
        } catch (\Throwable $e) {
            $this->logger("Surfaced while deleting: $e");
            $this->respondError($message, $e);
            return;
        }
        try {
            $this->respondOrDelete($message, $response);
        } catch (\Throwable) {
        }
    }
    #endregion


    public function getReportPeers(): array
    {
        return [ADMIN];
    }
}
