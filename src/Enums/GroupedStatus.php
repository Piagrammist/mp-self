<?php declare(strict_types=1);

namespace Rz\Enums;

enum GroupedStatus: string
{
    case FAIL = 'fail';
    case PARTIAL = 'partial';
    case SUCCESS = 'success';

    public static function fromStates(?bool ...$states): self
    {
        $sum = \array_sum($states);

        if ($sum === 0)
            return self::FAIL;

        if ($sum === \count($states))
            return self::SUCCESS;

        return self::PARTIAL;
    }
}
