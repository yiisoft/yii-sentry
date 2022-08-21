<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Integration\RequestFetcher;
use Sentry\Integration\RequestFetcherInterface;

use const PHP_SAPI;

class YiiRequestFetcher implements RequestFetcherInterface
{
    /**
     * The Laravel container.
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /** @psalm-suppress  MixedInferredReturnType */
    public function fetchRequest(): ?ServerRequestInterface
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return null;
        }

        if ($this->container->has(ServerRequestInterface::class)) {
            /** @psalm-suppress  MixedAssignment */
            $result = $this->container->get(ServerRequestInterface::class);

            if ($result instanceof ServerRequestInterface) {
                return $result;
            }
        }

        return (new RequestFetcher())->fetchRequest();
    }
}
