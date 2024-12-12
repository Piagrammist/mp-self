<?php declare(strict_types=1);

namespace Rz\Plugins;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdminOrOutgoing;

use Rz\Utils;
use Rz\Filters\FilterActive;

final class CopyPlugin extends PluginEventHandler
{
    use Utils;

    #[FiltersAnd(new FilterActive, new FilterCommand('cp'))]
    public function process(FromAdminOrOutgoing&Message $message): void
    {
        $peer    = $message->commandArgs[0] ?? $message->chatId;
        $replied = $message->getReply();
        if (!$replied)
            return;
        if (!$replied instanceof Message) {
            $this->respondError($message, "Replied message is not copyable!");
            return;
        }

        $this->loading($message);
        try {
            $this->getInfo($peer);
        } catch (\Throwable $e) {
            $this->respondError($message, $e);
            return;
        }
        $params = [
            'peer'    => $peer,
            'message' => $replied->message,
        ];
        if (!empty($replied->media))    $params['media']    = $replied->media;
        if (!empty($replied->entities)) $params['entities'] = $replied->entities;
        $this->messages->{!empty($replied->media) ? 'sendMedia' : 'sendMessage'}(...$params);
        $this->respondOrDelete($message, \sprintf(
            "Message copy sent to %s.",
            $peer === 'me' ? 'Saved Messages' : $peer,
        ));
    }
}
