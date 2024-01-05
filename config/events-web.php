<?php

declare(strict_types=1);

use Yiisoft\Middleware\Dispatcher\Event\AfterMiddleware;
use Yiisoft\Middleware\Dispatcher\Event\BeforeMiddleware;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;
use Yiisoft\Yii\Sentry\Tracing\SentryTraceWebListener;

if (empty($params['yiisoft/yii-sentry']['options']['dsn'])) {
    return [];
}

return [
    ApplicationShutdown::class => [
        [SentryTraceWebListener::class, 'listen'],
    ],
    BeforeMiddleware::class => [
        [SentryTraceWebListener::class, 'listen'],
    ],
    AfterMiddleware::class => [
        [SentryTraceWebListener::class, 'listen'],
    ],
];
