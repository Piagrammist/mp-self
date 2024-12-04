<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class StylePlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('style'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $newStyle = \strtolower($message->commandArgs[0]);
        if (!self::validateStyle($newStyle))
            return;

        $this->styleChar = $newStyle === 'none' ? null : $newStyle;
        $this->respondOrDelete($message, \sprintf(
            "Successfully switched to %s text styling.",
            self::$allowedStyles[$newStyle],
        ));
    }

    public static array $allowedStyles = [
        '*'    => 'bold',
        '_'    => 'italic',
        '__'   => 'underline',
        '`'    => 'monospace',
        '~'    => 'strikethrough',
        'none' => 'no',
    ];

    private ?string $styleChar = '_';

    public function __sleep(): array
    {
        return ['styleChar'];
    }

    public function getStyle(): string
    {
        return $this->styleChar;
    }
    public function setStyle(string $char): self
    {
        if (!self::validateStyle($char)) {
            throw new \InvalidArgumentException('Invalid style character provided');
        }
        $this->styleChar = $char;
        return $this;
    }

    public static function validateStyle(string $char): bool
    {
        return \array_key_exists($char, self::$allowedStyles);
    }

    public static function nameOf(string $char): string
    {
        if ($char === null)
            $char = 'none';

        if (!isset(self::$allowedStyles[$char]))
            throw new \InvalidArgumentException('Invalid style character provided');

        return self::$allowedStyles[$char];
    }
}
