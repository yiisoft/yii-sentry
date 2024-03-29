<?php

declare(strict_types=1);

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Yiisoft\Yii\Sentry\SentryConsoleHandler;

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
];
