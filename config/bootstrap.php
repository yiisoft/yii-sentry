<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

return [
    static function (ContainerInterface $container): void {
        $options = $container->get(Options::class);

        $clientBuilder = new ClientBuilder($options);
        if ($options->getLogger() === null && $container->has(LoggerInterface::class)) {
            $clientBuilder->setLogger($container->get(LoggerInterface::class));
        }

        $client = $clientBuilder->getClient();

        $hub = $container->get(HubInterface::class);
        $hub->bindClient($client);

        SentrySdk::setCurrentHub($hub);
    },
];
