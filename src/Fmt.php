<?php declare(strict_types=1);

namespace Rz;

use Rz\Plugins\StylePlugin;

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

    public static function bool(?bool $switch): string
    {
        return $switch !== null
            ? $switch ? '✓' : '✗'
            : StylePlugin::EMPTY;
    }

    public static function str(?string $text, bool $copyable = false): string
    {
        if (empty($text))
            return StylePlugin::EMPTY;

        if ($copyable) {
            $text = "`$text`";
        }
        return $text;
    }

    private const REQ_BIRTH_FIELDS = ['day', 'month'];
    public static function birth(?array $parts, bool $copyable = false): string
    {
        if (empty($parts))
            return StylePlugin::EMPTY;

        requireArrayKeys($parts, self::REQ_BIRTH_FIELDS);
        $result = empty($parts['year'])
            ? \sprintf('%s/%s',    $parts['month'], $parts['day'])
            : \sprintf('%s/%s/%s', $parts['month'], $parts['day'], $parts['year']);
        if ($copyable) {
            $result = "`$result`";
        }
        return $result;
    }
}
