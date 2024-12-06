<?php declare(strict_types=1);

namespace Rz;

final class Fmt
{
    public static function json($value): string
    {
        return \sprintf("```json\n%s```", escape('```', toJSON($value)));
    }

    public static function error(\Throwable|string $e): string
    {
        if ($e instanceof \Throwable) {
            $e = $e->getMessage();
        }
        return \sprintf("*Error:*\n```\n%s```", escape('```', $e));
    }

    public static function bool(bool $switch): string
    {
        return $switch ? '✓' : '✗';
    }
}
