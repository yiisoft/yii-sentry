<?php

declare(strict_types=1);

use Yiisoft\Yii\Sentry\SentryProvider;

/**
 * @var array $params
 */

$enabled = (bool)($params['yiisoft/yii-sentry']['enabled'] ?? true);

if (!$enabled) {
    return [];
}

return [
    SentryProvider::class => SentryProvider::class,
];
