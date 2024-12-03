<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\Logger;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;
use function Rz\concatLines;
use function Rz\readableBytes;

use function Amp\File\getSize;

final class StatusPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('status'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        $this->loading($message);

        $chats = [
            'user' => 0,
            'chat' => 0,
            'supergroup' => 0,
            'channel' => 0,
            'bot' => 0,
        ];
        foreach ($this->getDialogIds() as $peer) {
            $chats[$this->getInfo($peer)['type']]++;
        }
        $total = \array_sum($chats);

        $mem = readableBytes(\memory_get_usage());
        $peakMem = readableBytes(\memory_get_peak_usage());
        $loggerSettings = $this->getSettings()->getLogger();
        $log = $loggerSettings->getType() === Logger::FILE_LOGGER
            ? readableBytes(getSize($loggerSettings->getExtra()))
            : 'NaN';

        $message->editText(
            concatLines(
                "*Chats*",
                $this->prefix(
                    "_Total: {$total}_",
                    "_Bots: {$chats['bot']}_",
                    "_Users: {$chats['user']}_",
                    "_Groups: {$chats['chat']}_",
                    "_Supergroups: {$chats['supergroup']}_",
                    "_Channels: {$chats['channel']}_"
                ),
                \str_repeat('—', 13),
                "*Server*",
                $this->prefix(
                    "_RAM usage: {$mem}_",
                    "_Peak RAM usage: {$peakMem}_",
                    "_Log volume: {$log}_"
                ),
            ),
            ParseMode::MARKDOWN,
        );
    }
}
