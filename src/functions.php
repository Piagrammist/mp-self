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

/** @param array<string>|string $chars */
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

function toJSON($value): string
{
    return \json_encode($value,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );
}

function concatLines(string ...$lines): string
{
    return \implode("\n", $lines);
}

function splitLines(string $text): array
{
    return \explode("\n", \str_replace("\r", '', $text));
}

/**
 * @param array<string|int>|string|int $requiredKeys
 * @param bool $strict If `true`, use `empty()` for validation, `isset()` otherwise.
 * @throws \InvalidArgumentException
 */
function requireArrayKeys(
    array $haystack,
    array|string|int $requiredKeys,
    bool $strict = true,
): void {
    if (!is_array($requiredKeys)) {
        $requiredKeys = [$requiredKeys];
    }
    $validator = $strict
        ? static fn($v) =>  empty($v)
        : static fn($v) => !isset($v);
    $errorText = 'Required array field "%s" ' . ($strict ? 'is empty' : 'not set');
    foreach ($requiredKeys as $key) {
        if ($validator($haystack[$key] ?? null)) {
            throw new \InvalidArgumentException(\sprintf($errorText, $key));
        }
    }
}
