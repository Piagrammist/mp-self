<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\ParseMode;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;
use function Rz\concatLines;

final class InfoPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('info'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        $this->loading($message);

        $chat = $this->getInfo($message->chatId);
        if (!\in_array($chat['type'], ['user', 'bot'], true)) {
            $userId = ($replied = $message->getReply())
                ? $replied->senderId
                : $message->commandArgs[0]
                    ?? null;
            $username = isset($chat['Chat']['username']) ? "`{$chat['Chat']['username']}`" : StylePlugin::EMPTY;
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
                $username = isset($user['username']) ? "`{$user['username']}`" : StylePlugin::EMPTY;
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
}
