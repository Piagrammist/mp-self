<?php declare(strict_types=1);

namespace Rz;

function readableBytes(float $bytes, bool $si = false, int $dp = 2): string
{
    $thresh = $si ? 1000 : 1024;
    if (\abs($bytes) < $thresh) {
        return "$bytes B";
    }

    $units = $si
        ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
        : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    $u = -1;
    $r = 10 ** $dp;
    $count = \count($units) - 1;

    do {
        $bytes /= $thresh;
        ++$u;
    } while (\round(\abs($bytes) * $r) / $r >= $thresh && $u < $count);

    return \round($bytes, $dp) . ' ' . $units[$u];
}

function escape(array|string $chars, string $text): string
{
    if (empty($chars)) {
        return $text;
    }
    $replacement = \is_string($chars)
        ? "\\$chars"
        : \array_map(static fn($c) => "\\$c", $chars);
    return \str_replace($chars, $replacement, $text);
}

function fmtError(\Throwable|string $e): string
{
    return "*Error:*\n```\n$e```";
}

function concatLines(string ...$lines): string
{
    return \implode("\n", $lines);
}

function splitLines(string $text): array
{
    return \explode("\n", \str_replace("\r", '', $text));
}
