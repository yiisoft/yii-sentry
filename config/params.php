<?php

declare(strict_types=1);

use Sentry\Event;

return [
    'yiisoft/yii-sentry' => [
        'handleConsoleErrors' => true,
        'options' => [
            'dsn' => null,
            'before_send' => static function (Event $event): ?Event {
                foreach ($event->getExceptions() as $exception) {
                    if ($exception->getType() === 'Yiisoft\ErrorHandler\Exception\ErrorException') {
                        return null;
                    }
                }

                return $event;
            },
        ],
    ],
];
