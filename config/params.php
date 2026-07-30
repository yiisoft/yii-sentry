<?php

declare(strict_types=1);

use Sentry\Event;
use Yiisoft\ErrorHandler\Exception\ErrorException;

return [
    'yiisoft/yii-sentry' => [
        'handleConsoleErrors' => true,
        'cron-monitoring' => [],
        'options' => [
            'dsn' => null,
            'before_send' => static function (Event $event): ?Event {
                foreach ($event->getExceptions() as $exception) {
                    if ($exception->getType() === ErrorException::class) {
                        return null;
                    }
                }

                return $event;
            },
        ],
    ],
];
