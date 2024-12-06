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

        //!  change the class name[space] if needed.
        $code = "use Rz\Fmt;\n" . \mb_substr($message->message, 2);
        try {
            \ob_start();
            eval($code);
            $output = \ob_get_clean();
            $output = $output ? "*Result:*\n$output" : $this->style("No output.");
        } catch (\Throwable $e) {
            $output = Fmt::error($e);
        } finally {
            $message->reply($output, ParseMode::MARKDOWN);
        }
    }
}
