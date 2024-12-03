<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class BasicsPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('ping'))]
    public function processPing(FromAdminOrOutgoing&Message $message): void
    {
        $this->respondOrDelete($message, 'Pong!');
    }

    #[FiltersAnd(new FilterActive, new FilterCommand('stop'))]
    public function processStop(FromAdminOrOutgoing&Message $message): void
    {
        $this->respondOrDelete($message, 'Stopping...');
        $this->stop();
    }

    #[FiltersAnd(new FilterActive, new FilterCommand('restart'))]
    public function processRestart(FromAdminOrOutgoing&Message $message): void
    {
        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $this->respondError($message, "Restart not available on the CLI!");
            return;
        }
        $this->respondOrDelete($message, 'Restarting...');
        $this->restart();
    }
}
