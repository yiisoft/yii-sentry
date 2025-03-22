<?php

declare(strict_types=1);

use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;

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
];
