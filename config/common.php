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
            // TODO use composer tool
            'sdkVersion' => \Jean85\PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion(),
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
