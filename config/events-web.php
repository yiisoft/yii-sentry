<?php

declare(strict_types=1);

use Yiisoft\Middleware\Dispatcher\Event\AfterMiddleware;
use Yiisoft\Middleware\Dispatcher\Event\BeforeMiddleware;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;
use Yiisoft\Yii\Sentry\Tracing\EventWebTraceHandler;

if (empty($params['sentry']['options']['dsn'])) {
    return [];
}

return [
    ApplicationShutdown::class => [
        [EventWebTraceHandler::class, 'listen'],
    ],
    BeforeMiddleware::class => [
        [EventWebTraceHandler::class, 'listen'],
    ],
    AfterMiddleware::class => [
        [EventWebTraceHandler::class, 'listen'],
    ],
];
