<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Sentry\Client as SentryClient;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\HttpClient\HttpClientFactoryInterface;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\DefaultTransportFactory;
use Sentry\Transport\TransportFactoryInterface;
use Yiisoft\Yii\Sentry\YiiSentryConfig;

/**
 * @var $params array
 */

return [
    YiiSentryConfig::class => [
        '__construct()' => [
            'config' => $params['yiisoft/yii-sentry'],
        ],
    ],
    TransportFactoryInterface::class => DefaultTransportFactory::class,
    HttpClientFactoryInterface::class => [
        'class' => HttpClientFactory::class,
        '__construct()' => [
            'sdkIdentifier' => SentryClient::SDK_IDENTIFIER,
            'sdkVersion' => InstalledVersions::getPrettyVersion('sentry/sdk'),
        ],
    ],
    Options::class => [
        'class' => Options::class,
        '__construct()' => [
            $params['yiisoft/yii-sentry']['options'],
        ],
    ],

    HubInterface::class => Hub::class,
];
