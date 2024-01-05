<?php

declare(strict_types=1);

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Yiisoft\Yii\Console\Event\ApplicationShutdown;
use Yiisoft\Yii\Console\Event\ApplicationStartup;
use Yiisoft\Yii\Sentry\SentryConsoleHandler;
use Yiisoft\Yii\Sentry\Tracing\SentryTraceConsoleListener;

/**
 * @var $params array
 */

$enabled = $params['yiisoft/yii-sentry']['handleConsoleErrors'] ?? false;
if (!$enabled) {
    return [];
}

$dsnSet = (bool) ($params['yiisoft/yii-sentry']['options']['dsn'] ?? false);
if (!$dsnSet) {
    return [];
}

return [
    ConsoleErrorEvent::class => [
        [SentryConsoleHandler::class, 'handle'],
    ],
    ApplicationStartup::class => [
        [SentryTraceConsoleListener::class, 'listenAppStart'],
    ],
    ConsoleCommandEvent::class => [
        [SentryTraceConsoleListener::class, 'listenBeginCommand'],
    ],
    ConsoleTerminateEvent::class => [
        [SentryTraceConsoleListener::class, 'listenCommandTerminate'],
    ],
    ApplicationShutdown::class => [
        [SentryTraceConsoleListener::class, 'listenShutdown'],
    ],
];
