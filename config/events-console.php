<?php

declare(strict_types=1);

$enabled = (bool)($params['yiisoft/yii-sentry']['enabled'] ?? true);

if (!$enabled) {
    return [];
}

return [
    \Symfony\Component\Console\Event\ConsoleErrorEvent::class => [
        [\Yiisoft\Yii\Sentry\SentryConsoleHandler::class, 'handle'],
    ],
];
