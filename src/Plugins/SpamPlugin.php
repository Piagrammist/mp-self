<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class SpamPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('spam'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
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
        $replyTo = $message->replyToMsgId;
        $this->periodicAction($messageCount, function () use ($chatId, $toSend, $replyTo) {
            $this->sendMessage($chatId, $toSend, replyToMsgId: $replyTo);
        });
    }
}
