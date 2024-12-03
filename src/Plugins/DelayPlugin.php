<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class DelayPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('delay'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $delay = (int)$message->commandArgs[0];
        if (!self::validateDelay($delay)) {
            $this->respondError($message, "Delay must not be a negative number!");
            return;
        }

        $this->delay = \round($delay / 1e3, 2);
        $this->respondOrDelete($message, "Operation delay changed to {$this->delay}s.");
    }

    private float $delay = 0.5;

    public function __sleep(): array
    {
        return ['delay'];
    }

    public function getDelay(): float
    {
        return $this->delay;
    }
    public function setDelay(float $delay): self
    {
        if (!self::validateDelay($delay)) {
            throw new \RangeException('Delay must be greater than zero');
        }
        $this->delay = $delay;
        return $this;
    }

    public static function validateDelay(float $delay): bool
    {
        return $delay >= 0;
    }
}
