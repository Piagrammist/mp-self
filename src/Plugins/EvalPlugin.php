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
use function Rz\fmtError;

final class EvalPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('x'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $code = \mb_substr($message->message, 2);
        $toJSON = fn ($value): string =>
            \json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $tgJSON = fn ($value): string =>
            "```json\n" . $toJSON($value) . "```";
        try {
            \ob_start();
            eval($code);
            $output = \ob_get_clean();
            $output = $output ? "*Result:*\n$output" : $this->style("No output.");
        } catch (\Throwable $e) {
            $output = fmtError($e);
        } finally {
            $message->reply($output, ParseMode::MARKDOWN);
        }
    }
}
