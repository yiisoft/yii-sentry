<?php

declare(strict_types=1);

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Yiisoft\Yii\Sentry\SentryConsoleHandler;
use Yiisoft\Yii\Sentry\SentryCronMonitor;

/**
 * @var $params array
 */

$dsnSet = (bool) ($params['yiisoft/yii-sentry']['options']['dsn'] ?? false);
if (!$dsnSet) {
    return [];
}

$events = [];

$handleErrors = $params['yiisoft/yii-sentry']['handleConsoleErrors'] ?? false;
if ($handleErrors) {
    $events[ConsoleErrorEvent::class][] = [SentryConsoleHandler::class, 'handle'];
}

$monitoring = $params['yiisoft/yii-sentry']['cron-monitoring'] ?? [];
if (is_array($monitoring) && $monitoring !== []) {
    $events[ConsoleCommandEvent::class][] = [SentryCronMonitor::class, 'handleCommand'];
    $events[ConsoleTerminateEvent::class][] = [SentryCronMonitor::class, 'handleTerminate'];
}

return $events;
