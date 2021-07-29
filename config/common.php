<?php

declare(strict_types=1);

/**
 * @var $params array
 */

return [
    \Sentry\Transport\TransportFactoryInterface::class => \Sentry\Transport\DefaultTransportFactory::class,
    \Sentry\HttpClient\HttpClientFactoryInterface::class => function (Yiisoft\Injector\Injector $injector) {
        return $injector->make(\Sentry\HttpClient\HttpClientFactory::class, [
            'sdkIdentifier' => \Sentry\Client::SDK_IDENTIFIER,
            // TODO use composer tool
            'sdkVersion' => \Jean85\PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion(),
        ]);
    },
    \Sentry\Options::class => function () use ($params) {
        return new \Sentry\Options($params['yiisoft/yii-sentry']['options']);
    },
    \Sentry\State\HubInterface::class => \Sentry\State\Hub::class,
];
