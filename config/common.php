<?php

declare(strict_types=1);

/**
 * @var $params array
 */

return [
    \Sentry\Transport\TransportFactoryInterface::class => \Sentry\Transport\DefaultTransportFactory::class,
    \Sentry\HttpClient\HttpClientFactoryInterface::class => [
        'class' => \Sentry\HttpClient\HttpClientFactory::class,
        '__construct()' => [
            'sdkIdentifier' => \Sentry\Client::SDK_IDENTIFIER,
            'sdkVersion' => \Composer\InstalledVersions::getPrettyVersion('sentry/sentry'),
        ],
    ],
    \Sentry\Options::class => [
        'class' => \Sentry\Options::class,
        '__construct()' => [
            $params['yiisoft/yii-sentry']['options'],
        ],
    ],
    \Sentry\State\HubInterface::class => \Sentry\State\Hub::class,
];
