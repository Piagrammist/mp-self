<?php

$path = $argv[1] ?? './MadelineProto.log';
if (!is_file($path)) {
    echo "Usage: php {$argv[0]} <path_to_file>", PHP_EOL;
    echo "  file does not exist at '$path'!", PHP_EOL;
    exit(1);
}

file_put_contents(
    $path,
    preg_replace(
        '~\x1b\[(?:\d{1,2};)?(?:\d{1,2};)?\d{1,2}[mGKHF]~',
        '',
        file_get_contents($path)
    )
);
echo 'done!', PHP_EOL;
