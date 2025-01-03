<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\ParseMode;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\CommandType;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;
use function Rz\escape;
use function Rz\concatLines;

final class HelpPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('help'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        $styles = escape('`', \implode('|', \array_keys(StylePlugin::$allowedStyles)));
        $prefixes = /*escape(['`', '_'], */
            \implode(
                '  ',
                \array_map(
                    static fn($e) => "`$e`",
                    \array_column(CommandType::cases(), 'value')
                )
            )/*)*/
        ;
        $help = concatLines(
            '*Robot commands*',

            '',
            '`.bot <on|off>`',
            '_Make the robot active or inactive._',

            '',
            '`.quiet <on|off>`',
            "_Switch the robot's quiet mode._",

            '',
            '`.delay <num_x>`',
            "_Change the periodic actions' delay._",

            '',
            '`.ping`',
            "_Check robot's responding._",

            '',
            '`.x <code>`',
            "_Execute the php code._",

            '',
            '`.cp <?peer> (reply)`',
            "_Copy and send the replied message to any chat. (default peer: current chat)_",

            '',
            '`.info <?peer> [reply]`',
            "_Get info about the chat (+ a user depending on the reply/arg value)._",

            '',
            '`.status`',
            "_Get info about the server & robot's chats._",

            '',
            "`.style <{$styles}>`",
            '_Switch the text styling._',

            '',
            '`.spell <txt>`',
            '_Split text into letters and send them separately._',

            '',
            '`.spam <num_x> <?num_y> <txt>`',
            '_Send x messages, each containing y \* txt. (y can be omitted!)_',

            '',
            '`.del <num_x> <?s|service>/<?a|after> [reply]`',
            '_Delete x messages from the chat._',
            '_- If `service` is set, only service messages will be deleted._',
            '_- If replied to a message, only messages before (after, if set) the replied message will be deleted._',

            '',
            '`.backup <?s|show>/<?c|clear>`',
            '_Make a profile backup, or show/delete the previous one._',

            '',
            '`.restore`',
            '_Restore the profile backup if exists._',

            '',
            '`.clone <?peer> [reply]`',
            '_Clone profile of the peer, replied user or current chat._',

            '',
            '`{} (reply)`',
            "_Get JSON view of the replied message update._",

            // '',
            // '``',
            // '__',

            '',
            \str_repeat('—', 13),
            "*Notes*",
            $this->prefix(
                \sprintf('_Supported command prefixes are "%s"_', $prefixes),
                '_`()` indicates a required reply, and `[]`, an optional one._',
                '_`/` between arguments indicates "or"._',
            ),
        );
        $message->editText($help, ParseMode::MARKDOWN);
    }
}
