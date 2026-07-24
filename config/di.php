<?php

declare(strict_types=1);

use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Yiisoft\Yii\Sentry\SentryCronMonitor;

/**
 * @var $params array
 */

return [
    Options::class => [
        'class' => Options::class,
        '__construct()' => [
            $params['yiisoft/yii-sentry']['options'],
        ],
    ],
    HubInterface::class => Hub::class,
    SentryCronMonitor::class => [
        'class' => SentryCronMonitor::class,
        '__construct()' => [
            'monitoring' => $params['yiisoft/yii-sentry']['cron-monitoring'] ?? [],
        ],
    ],
];
