<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

use function Amp\now;

final class CleanerPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('del'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        $count = (int)($message->commandArgs[0] ?? 0);
        if ($count < 1)
            return;
        $argTwo   = \strtolower($message->commandArgs[1] ?? '');
        $argThree = \strtolower($message->commandArgs[2] ?? '');

        $after   = ['a', 'aft', 'after'];
        $service = ['s', 'ser', 'service'];
        $afterReply  = \in_array($argTwo, $after,   true) || \in_array($argThree, $after,   true);
        $serviceOnly = \in_array($argTwo, $service, true) || \in_array($argThree, $service, true);
        unset($argTwo, $argThree, $after, $service);

        $this->loading($message);

        $chunks = [];
        while ($count > 0) {
            $chunks []= $count > 99 ? 99 : $count;
            $count -= 99;
        }

        $params = [
            'peer' => $message->chatId,
            $afterReply ? 'min_id' : 'max_id' => (int)$message->getReply()?->id,
        ];
        try {
            $deleted = 0;
            $start = now();

            foreach ($chunks as $count) {
                while ($deleted < $count) {
                    $params['limit'] = $count + 1;
                    $history = $this->messages->getHistory(...$params)['messages'];
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
                        break 2;

                    // Don't surpass the `count`.
                    if (\count($ids) + $deleted > $count) {
                        /** @var int */
                        $length = $count - $deleted;
                        $ids = \array_slice($ids, 0, $length);
                    }
                    $deleted += $cycle = $this->deleteMessages($message->chatId, $ids)['pts_count'];
                    if ($cycle === 0)
                        break 2;
                }
            }

            if ($deleted === 0) {
                $response = "Could not delete any message!";
            } else {
                $diff = \round(now() - $start, 2);
                $response = \sprintf("Successfully deleted %s messages in %ss.",
                    $deleted === $count ? $deleted : "{$deleted}/{$count}",
                    $diff,
                );
            }
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
}
