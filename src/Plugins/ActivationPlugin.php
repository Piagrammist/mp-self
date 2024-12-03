<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;

final class ActivationPlugin extends PluginEventHandler
{
    use Utils;

    #[FilterCommand('bot')]
    public function process(FromAdminOrOutgoing&Message $message): void
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

    private bool $active = true;

    public function __sleep(): array
    {
        return ['active'];
    }

    public function getActive(): bool
    {
        return $this->active;
    }
    public function setActive(bool $active = true): self
    {
        $this->active = $active;
        return $this;
    }
}
