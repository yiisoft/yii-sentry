<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use HttpSoft\Message\RequestFactory;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UriFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Client as SentryClient;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\HttpClient\HttpClientFactoryInterface;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\DefaultTransportFactory;
use Sentry\Transport\TransportFactoryInterface;

/**
 * @var $params array
 */

return [
    TransportFactoryInterface::class => DefaultTransportFactory::class,
    StreamFactoryInterface::class => StreamFactory::class,
    RequestFactoryInterface::class => RequestFactory::class,
    LoggerInterface::class => NullLogger::class,
    UriFactoryInterface::class => UriFactory::class,
    ResponseFactoryInterface::class => ResponseFactory::class,
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
