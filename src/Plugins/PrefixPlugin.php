<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class PrefixPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('prefix'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $newPrefix = $message->commandArgs[0];
        $this->prefixChar = $newPrefix;
        $this->respondOrDelete($message, "Successfully changed the prefix to $newPrefix.");
    }

    private ?string $prefixChar = '❍';

    public function __sleep(): array
    {
        return ['prefixChar'];
    }

    public function getPrefix(): string
    {
        return $this->prefixChar;
    }
    public function setPrefix(string $char): self
    {
        $this->prefixChar = $char;
        return $this;
    }
}
