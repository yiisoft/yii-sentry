<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Transport\TransportFactoryInterface;

return [
    static function (ContainerInterface $container): void {
        $options = $container->get(Options::class);

        $clientBuilder = new ClientBuilder($options);
        $clientBuilder
            ->setTransportFactory($container->get(TransportFactoryInterface::class))
            ->setLogger($container->get(LoggerInterface::class));

        $client = $clientBuilder->getClient();

        $hub = $container->get(HubInterface::class);
        $hub->bindClient($client);

        SentrySdk::setCurrentHub($hub);
    },
];
