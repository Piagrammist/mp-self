<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class VerbosityPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('quiet'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
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

    private bool $verbose = true;

    public function __sleep(): array
    {
        return ['verbose'];
    }

    public function getVerbose(): bool
    {
        return $this->verbose;
    }
    public function setVerbose(bool $verbose = true): self
    {
        $this->verbose = $verbose;
        return $this;
    }
}
