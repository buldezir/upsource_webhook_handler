<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes.php';

$logger = new Logger('WebHook');
$logger->pushHandler(new StreamHandler(__DIR__ . '/upsource-webhooks.log'));
$logger->pushHandler(new StreamHandler('php://stdout'));

$upsourceApi = new UpSourceClient(new UpSourceConfiguration(require __DIR__.'/config.php'));

$jiraApi = new JiraClient(new JiraConfiguration(require __DIR__.'/config.php'));

/** @var array $upsourceData */
$upsourceData = json_decode(file_get_contents('php://input'), true);

if (!isset($upsourceData['dataType'])) {
    // dataType это знавание ивента апсорса
    throw new \LogicException('dataType is required');
}

/** @var string $eventType */
$eventType = $upsourceData['dataType'];
$handlerFile = 'upsource.' . $eventType . '.php';

if (!file_exists($handlerFile)) {
    throw new \LogicException("no handler for {$eventType}");
}

$logger->info("Handling {$eventType} event");

require_once $handlerFile;