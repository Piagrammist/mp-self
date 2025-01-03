<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\ParseMode;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Fmt;
use Rz\Utils;
use Rz\Filters\FilterActive;

final class EvalPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('x'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        if (!isset($message->commandArgs[0]))
            return;

        $code = \sprintf("use %s;\n", Fmt::class) . \implode(' ', $message->commandArgs);
        try {
            \ob_start();
            eval($code);
            $output = \ob_get_clean();
            $hasOutput = !empty($output);
            if (!$hasOutput && !$this->isVerbose())
                return;
            $message->reply(
                $hasOutput ? "*Result:*\n$output" : $this->style("No output."),
                ParseMode::MARKDOWN,
            );
        } catch (\Throwable $e) {
            if (\ob_get_length()) {
                \ob_end_clean();
            }
            if ($e->getMessage() === 'MESSAGE_EMPTY' && \strlen($output) > 0) {
                $this->respondError($message, "Invalid output characters!");
                return;
            }
            $this->respondError($message, $e);
        }
    }
}
