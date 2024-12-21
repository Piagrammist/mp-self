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
        $total = (int)($message->commandArgs[0] ?? 0);
        if ($total < 1)
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
        for ($c = $total; $c > 0; $c -= 99) {
            $chunks []= $c > 99 ? 99 : $c;
        }

        $params = [
            'peer'   => $message->chatId,
            'max_id' => $message->id,
        ];
        if ($message->isReply()) {
            $replyId = $message->getReply()->id;

            if ($afterReply)
                $params['min_id'] = $replyId - 1;
            else
                $params['offset_id'] = $replyId + 1;
        }

        try {
            $deleted = 0;
            $toBreak = false;
            $start = now();

            foreach ($chunks as $limit) {
                $params['limit'] = $limit + 1;
                $history = $this->messages->getHistory(...$params)['messages'];
                if ($serviceOnly) {
                    $history = \array_values(\array_filter($history,
                        static fn($msg) => $msg['_'] === 'messageService'));
                }
                // Filter current topic messages.
                $history = \array_values(\array_filter(
                    \array_map($this->wrapMessage(...), $history),
                    static fn($msg) => $msg->topicId === $message->topicId,
                ));

                $ids = \array_column($history, 'id');
                if (\count($ids) === 0)
                    break;

                // Don't surpass the `total` number.
                if (\count($ids) + $deleted > $total) {
                    /** @var int */
                    $length = $total - $deleted;
                    $ids = \array_slice($ids, 0, $length);
                    $toBreak = true;
                }
                $deleted += $cycle = $this->deleteMessages($message->chatId, $ids)['pts_count'];
                if ($cycle === 0 || $toBreak)
                    break;
            }

            if ($deleted === 0) {
                $response = "Could not delete any message!";
            } else {
                $diff = \round(now() - $start, 2);
                $response = \sprintf("Managed to delete %s messages in %ss.",
                    $deleted === $total ? $deleted : "{$deleted}/{$total}",
                    $diff,
                );
            }
        } catch (\Throwable $e) {
            $this->logger("Surfaced while deleting: $e");
            $this->respondError($message, $e);
            return;
        }
        $this->ignoreErrors(fn() => $this->respondOrDelete($message, $response));
    }
}
