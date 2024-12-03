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
        $argOne = $message->commandArgs[0] ?? null;
        $argTwo = (int)($message->commandArgs[1] ?? 0);
        $serviceOnly = \in_array(\strtolower($argOne), ['s', 'service'], true);
        $count = (int)$argOne;
        if ($serviceOnly) {
            if (!$argTwo)
                return;

            $count = $argTwo;
        } elseif ($count < 1) {
            return;
        }

        $this->loading($message);

        $counts = [];
        while ($count > 0) {
            $counts []= $count > 99 ? 99 : $count;
            $count -= 99;
        }
        $offsetDate = $message->isReply()
            ? $message->getReply()->date
            : 0;
        try {
            $deleted = 0;
            $start = now();

            foreach ($counts as $count) {
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
                        break 2;

                    // Don't surpass the `count`.
                    if (\count($ids) + $deleted > $count) {
                        $ids = \array_slice($ids, 0, (int)($count - $deleted));
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
