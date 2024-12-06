<?php declare(strict_types=1);

namespace Rz;

use danog\DialogId\DialogId;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\EventHandler\Message;

use Rz\Plugins\DelayPlugin;
use Rz\Plugins\StylePlugin;
use Rz\Plugins\PrefixPlugin;
use Rz\Plugins\VerbosityPlugin;
use Rz\Plugins\ActivationPlugin;

trait Utils
{
    public function deleteMessages(int $chatId, array $ids, bool $revoke = true): array
    {
        if (DialogId::isSupergroupOrChannel($chatId)) {
            return $this->channels->deleteMessages(channel: $chatId, id: $ids);
        }
        return $this->messages->deleteMessages(revoke: $revoke, id: $ids);
    }

    public function loading(Message $message, ?string $customMessage = null): void
    {
        $message->editText($this->style($customMessage ?: 'Processing...'), ParseMode::MARKDOWN);
    }
    public function loadingOrDelete(Message $message, ?string $customMessage = null): void
    {
        $this->isVerbose()
            ? $this->loading($message, $customMessage)
            : $message->delete();
    }

    public function deleteIfQuiet(Message $message): void
    {
        $this->isVerbose() || $message->delete();
    }

    public function respondIfVerbose(Message $message, string $text): void
    {
        $this->isVerbose() &&
            $message->editText($this->style($text), ParseMode::MARKDOWN);
    }
    public function respondOrDelete(Message $message, string $text): void
    {
        $this->isVerbose()
            ? $message->editText($this->style($text), ParseMode::MARKDOWN)
            : $message->delete();
    }
    public function respondError(Message $message, \Throwable|string $e): void
    {
        $message->reply(Fmt::error($e), ParseMode::MARKDOWN);
    }

    public function style(string $text): string
    {
        $style = $this->getStyle();
        if ($style === null) {
            return $text;
        }
        return concatLines(...\array_map(
            static fn($l) => "$style{$l}$style",
            splitLines($text),
        ));
    }
    public function prefix(string ...$lines): string
    {
        $prefix = $this->getPrefix();
        return concatLines(...(
            $prefix !== null
                ? \array_map(static fn($l) => "$prefix $l", $lines)
                : $lines
        ));
    }

    public function periodicAction(int|array $data, callable $cb): void
    {
        if ($delay = $this->getDelay()) {
            $cb = \is_array($data)
                ? static function ($item, $i) use ($cb, $delay): void {
                    $cb($item, $i);
                    self::sleep($delay);
                }
                : static function ($i) use ($cb, $delay): void {
                    $cb($i);
                    self::sleep($delay);
                };
        }
        if (\is_array($data)) {
            \array_walk($data, $cb);
            return;
        }
        for ($i = 0; $i < $data; $i++) {
            $cb($i);
        }
    }

    protected function isActive(): bool
    {
        return $this->getPlugin(ActivationPlugin::class)->getActive();
    }
    protected function isVerbose(): bool
    {
        return $this->getPlugin(VerbosityPlugin::class)->getVerbose();
    }
    protected function getStyle(): string
    {
        return $this->getPlugin(StylePlugin::class)->getStyle();
    }
    protected function getPrefix(): string
    {
        return $this->getPlugin(PrefixPlugin::class)->getPrefix();
    }
    protected function getDelay(): float
    {
        return $this->getPlugin(DelayPlugin::class)->getDelay();
    }
}
