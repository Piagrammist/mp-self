<?php declare(strict_types=1);

use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Stream\Proxy\SocksProxy;

require __DIR__ . '/vendor/autoload.php';

$settings = new Settings;
$settings->getLogger()
    ->setType(Logger::FILE_LOGGER);
$settings->getAppInfo()
    ->setApiId(API_ID)
    ->setApiHash(API_HASH);

if (constant('PROXY')) {
    $proxy = explode(':', PROXY, 2);
    if (count($proxy) === 2) {
        $settings->getConnection()
            ->addProxy(SocksProxy::class, [
                'address' => $proxy[0],
                'port'    => $proxy[1],
            ]);
    }
}

is_dir(SESSION_DIR) || mkdir(SESSION_DIR, recursive: true);
Rz\EventHandler::startAndLoop(realpath(SESSION_DIR), $settings);
