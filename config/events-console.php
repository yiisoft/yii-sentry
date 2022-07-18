<?php

declare(strict_types=1);

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Yiisoft\Yii\Sentry\SentryConsoleHandler;

$enabled = $params['yiisoft/yii-sentry']['handleConsoleErrors'] ?? false;
if (!$enabled) {
    return [];
}

return [
    ConsoleErrorEvent::class => [
        [SentryConsoleHandler::class, 'handle'],
    ],
];
