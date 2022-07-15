<?php

declare(strict_types=1);

/**
 * @var $params array
 */

use Composer\InstalledVersions;
use Sentry\Client as SentryClient;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\HttpClient\HttpClientFactoryInterface;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\DefaultTransportFactory;
use Sentry\Transport\TransportFactoryInterface;

return [
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
