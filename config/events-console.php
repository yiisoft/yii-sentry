<?php

declare(strict_types=1);

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Yiisoft\Yii\Sentry\SentryConsoleHandler;

$enabled = (bool)($params['yiisoft/yii-sentry']['options']['dsn'] ?? false);

if (!$enabled) {
    return [];
}

return [
    ConsoleErrorEvent::class => [
        [SentryConsoleHandler::class, 'handle'],
    ],
];
