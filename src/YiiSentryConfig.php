<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use InvalidArgumentException;

final class YiiSentryConfig
{
    public function __construct(protected array $config)
    {
    }

    /**
     * Check if a DSN was set in the config.
     */
    public function hasDsnSet(): bool
    {
        $config = $this->getUserConfig();

        return !empty($config['options']['dsn']);
    }

    /**
     * Retrieve the user configuration.
     */
    public function getUserConfig(): array
    {
        $config = $this->config;

        return empty($config) ? [] : $config;
    }

    public function getOptions(): array
    {
        if (empty($this->config['options'])) {
            return [];
        }
        return is_array($this->config['options'])
                ? $this->config['options']
                : throw  new InvalidArgumentException('options must be an array');
    }

    public function getTracing(): array
    {
        if (empty($this->config['tracing'])) {
            return [];
        }
        return is_array($this->config['tracing'])
                ? $this->config['tracing']
                : throw  new InvalidArgumentException('tracing must be an array');
    }

    public function getMaxGuzzleBodyTrace(): ?int
    {
        return empty($this->getTracing()['guzzle_max_body']) ? null : (int)$this->getTracing()['guzzle_max_body'];
    }

    public function getLogLevel(): ?string
    {
        return isset($this->config['log_level']) ? (string)$this->config['log_level'] : null;
    }

    public function getIntegrations(): array
    {
        if (empty($this->config['integrations'])) {
            return [];
        }
        return is_array($this->config['integrations'])
                ? $this->config['integrations']
                : throw  new InvalidArgumentException('integrations must be an array');
    }

    /**
     * Checks if the config is set in such a way that performance tracing could be enabled.
     *
     * Because of `traces_sampler` being dynamic we can never be 100% confident but that is also not important.
     */
    public function couldHavePerformanceTracingEnabled(): bool
    {
        $config = $this->getUserConfig();

        return !empty($config['options']['traces_sample_rate']) || !empty($config['options']['traces_sampler']);
    }
}
