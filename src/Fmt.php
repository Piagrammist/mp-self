<?php declare(strict_types=1);

namespace Rz;

final class Fmt
{
    public static function json($value): string
    {
        return "```json\n" . toJSON($value) . "```";
    }

    public static function error(\Throwable|string $e): string
    {
        return "*Error:*\n```\n$e```";
    }

    public static function bool(bool $switch): string
    {
        return $switch ? '✓' : '✗';
    }
}
