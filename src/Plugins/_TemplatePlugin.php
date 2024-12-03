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

final class _TemplatePlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('<CMD>'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
    }
}
