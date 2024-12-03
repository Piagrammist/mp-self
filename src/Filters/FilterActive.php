<?php declare(strict_types=1);

namespace Rz\Filters;

use Attribute;

use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\Update;
use danog\MadelineProto\EventHandler\Filter\Filter;

#[Attribute(Attribute::TARGET_METHOD)]
final class FilterActive extends Filter
{
    private EventHandler $API;

    public function initialize(EventHandler $API): Filter
    {
        $this->API = $API;
        return $this;
    }

    public function apply(Update $update): bool
    {
        return $this->API
            ->getPlugin(\Rz\Plugins\ActivationPlugin::class)
            ->getActive();
    }
}
