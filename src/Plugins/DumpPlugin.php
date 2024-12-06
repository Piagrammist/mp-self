<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\ParseMode;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterText;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Fmt;
use Rz\Utils;
use Rz\Filters\FilterActive;

final class DumpPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterText('{}'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        if (!($repliedTo = $message->getReply()))
            return;

        try {
            $message->reply(Fmt::json($repliedTo), ParseMode::MARKDOWN);
        } catch (\Throwable $e) {
            $this->respondError($message, $e);
        }
    }
}
