<?php declare(strict_types=1);

namespace Rz;

use Rz\Plugins;
use danog\MadelineProto\SimpleEventHandler;

final class EventHandler extends SimpleEventHandler
{
    public function getReportPeers(): array
    {
        return [ADMIN];
    }

    public static function getPlugins(): array
    {
        return [
            Plugins\ActivationPlugin::class,
            Plugins\BasicsPlugin::class,
            Plugins\CleanerPlugin::class,
            Plugins\CopyPlugin::class,
            Plugins\DelayPlugin::class,
            Plugins\EvalPlugin::class,
            Plugins\HelpPlugin::class,
            Plugins\InfoPlugin::class,
            Plugins\PrefixPlugin::class,
            Plugins\SpamPlugin::class,
            Plugins\SpellPlugin::class,
            Plugins\StatusPlugin::class,
            Plugins\StylePlugin::class,
            Plugins\VerbosityPlugin::class,
        ];
    }
}
