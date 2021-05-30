<?php

declare(strict_types=1);

/**
 * @var $params array
 */

return [
    \Http\Client\HttpClient::class => \GuzzleHttp\Client::class,
    \Http\Client\HttpAsyncClient::class => [
        'class' => \Http\Adapter\Guzzle7\Client::class,
        '__construct()' => [
            \Yiisoft\Factory\Definition\Reference::to(\GuzzleHttp\Client::class)
        ]
    ],

    \Sentry\Transport\TransportFactoryInterface::class => \Sentry\Transport\DefaultTransportFactory::class,
    \Sentry\HttpClient\HttpClientFactoryInterface::class => function (\Yiisoft\Injector\Injector $injector) {
        return $injector->make(\Sentry\HttpClient\HttpClientFactory::class, [
            'sdkIdentifier' => \Sentry\Client::SDK_IDENTIFIER,
            // TODO use composer tool
            'sdkVersion' => \Jean85\PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion(),
        ]);
    },
    \Sentry\Options::class => function () use ($params) {
        return new \Sentry\Options($params['yiisoft/yii-sentry']['options']);
    }
];
