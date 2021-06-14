<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Provider;

use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Transport\TransportFactoryInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\Support\ServiceProvider;

final class SentryProvider extends ServiceProvider
{
    /**
     * @psalm-suppress InaccessibleMethod
     */
    public function register(Container $container): void
    {
        $options = $container->get(Options::class);

        $clientBuilder = new ClientBuilder($options);
        $clientBuilder
            ->setTransportFactory($container->get(TransportFactoryInterface::class))
            ->setLogger($container->get(LoggerInterface::class));

        $client = $clientBuilder->getClient();

        $hub = $container->get(HubInterface::class);
        $hub->bindClient($client);

        SentrySdk::setCurrentHub($hub);
    }
}
