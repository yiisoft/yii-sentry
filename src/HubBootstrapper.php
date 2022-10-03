<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Integration as SdkIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Transport\TransportFactoryInterface;
use Yiisoft\Yii\Sentry\Http\YiiRequestFetcher;
use Yiisoft\Yii\Sentry\Integration\ExceptionContextIntegration;
use Yiisoft\Yii\Sentry\Integration\Integration;

use function is_string;

final class HubBootstrapper
{
    public const DEFAULT_INTEGRATIONS = [
        ExceptionContextIntegration::class
    ];

    public function __construct(
        private Options $options,
        private YiiSentryConfig $configuration,
        private TransportFactoryInterface $transportFactory,
        private LoggerInterface $logger,
        private HubInterface $hub,
        private ContainerInterface $container,
    ) {
    }

    public function bootstrap(): void
    {
        $this->options->setIntegrations(fn (array $integrations) => $this->prepareIntegrations($integrations));

        $clientBuilder = new ClientBuilder($this->options);
        $clientBuilder
            ->setTransportFactory($this->transportFactory)
            ->setLogger($this->logger);

        $client = $clientBuilder->getClient();

        $this->hub->bindClient($client);
        SentrySdk::setCurrentHub($this->hub);
    }

    /**
     * @param IntegrationInterface[] $integrations
     *
     * @return IntegrationInterface[]
     */
    public function prepareIntegrations(array $integrations): array
    {
        $userIntegrations = $this->resolveIntegrationsFromUserConfig();
        if (!$this->options->hasDefaultIntegrations()) {
            return array_merge($integrations, $userIntegrations);
        }

        $integrations = array_filter(
            $integrations,
            static function (SdkIntegration\IntegrationInterface $integration): bool {
                return !(
                    $integration instanceof SdkIntegration\ErrorListenerIntegration ||
                    $integration instanceof SdkIntegration\ExceptionListenerIntegration ||
                    $integration instanceof SdkIntegration\FatalErrorListenerIntegration ||
                    // We also remove the default request integration so it can be readded after with a Yii3
                    // specific request fetcher. This way we can resolve the request from Yii3 instead of
                    // constructing it from the global state.
                    $integration instanceof SdkIntegration\RequestIntegration
                );
            }
        );
        $integrations[] = new SdkIntegration\RequestIntegration(
            new YiiRequestFetcher($this->container)
        );

        return array_merge($integrations, $userIntegrations);
    }

    /**
     * Resolve the integrations from the user configuration with the container.
     *
     * @return SdkIntegration\IntegrationInterface[]
     */
    private function resolveIntegrationsFromUserConfig(): array
    {
        // Default Sentry SDK integrations
        $integrations = [
            new Integration(),
        ];

        $integrationsToResolve = $this->configuration->getIntegrations();

        $enableDefaultTracingIntegrations = array_key_exists('default_integrations', $this->configuration->getTracing())
            && (bool)$this->configuration->getTracing()['default_integrations'];

        if (
            $enableDefaultTracingIntegrations
            && $this->configuration->couldHavePerformanceTracingEnabled()
        ) {
            $integrationsToResolve = array_merge(
                $integrationsToResolve,
                self::DEFAULT_INTEGRATIONS
            );
        }
        /** @psalm-suppress MixedAssignment */
        foreach ($integrationsToResolve as $userIntegration) {
            if (
                $userIntegration instanceof
                SdkIntegration\IntegrationInterface
            ) {
                $integrations[] = $userIntegration;
            } elseif (is_string($userIntegration)) {
                /** @psalm-suppress MixedAssignment */
                $resolvedIntegration = $this->container->get($userIntegration);

                if (
                    !$resolvedIntegration instanceof
                        SdkIntegration\IntegrationInterface
                ) {
                    if (is_array($resolvedIntegration)) {
                        $value = 'array';
                    } elseif (is_object($resolvedIntegration)) {
                        $value = $resolvedIntegration::class;
                    } elseif (null === $resolvedIntegration) {
                        $value = 'null';
                    } else {
                        $value = (string)$resolvedIntegration;
                    }

                    throw new RuntimeException(
                        sprintf(
                            'Sentry integration must be an instance of `%s` got `%s`.',
                            SdkIntegration\IntegrationInterface::class,
                            $value
                        )
                    );
                }

                $integrations[] = $resolvedIntegration;
            } else {
                throw new RuntimeException(
                    sprintf(
                        'Sentry integration must either be a valid container reference or an instance of `%s`.',
                        SdkIntegration\IntegrationInterface::class
                    )
                );
            }
        }

        return $integrations;
    }
}
