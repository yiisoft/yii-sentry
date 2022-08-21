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
     *
     * @return bool
     */
    public function hasDsnSet(): bool
    {
        $config = $this->getUserConfig();

        return !empty($config['options']['dsn']);
    }

    /**
     * Retrieve the user configuration.
     *
     * @return array
     */
    public function getUserConfig(): array
    {
        $config = $this->config;

        return empty($config) ? [] : $config;
    }

    public function getOptions(): array
    {
        return empty($this->config['options'])
            ? []
            : (is_array($this->config['options'])
                ? $this->config['options']
                : throw  new InvalidArgumentException('options must be an array'));
    }

    public function getTracing(): array
    {
        return empty($this->config['tracing'])
            ? []
            : (is_array($this->config['tracing'])
                ? $this->config['tracing']
                : throw  new InvalidArgumentException('tracing must be an array'));
    }

    public function getLogLevel(): ?string
    {
        return isset($this->config['log_level']) ? (string)$this->config['log_level'] : null;
    }

    /**
     * @return array
     */
    public function getIntegrations(): array
    {
        return empty($this->config['integrations'])
            ? []
            : (is_array($this->config['integrations'])
                ? $this->config['integrations']
                : throw  new InvalidArgumentException('integrations must be an array'));
    }

    /**
     * Checks if the config is set in such a way that performance tracing could be enabled.
     *
     * Because of `traces_sampler` being dynamic we can never be 100% confident but that is also not important.
     *
     * @return bool
     */
    public function couldHavePerformanceTracingEnabled(): bool
    {
        $config = $this->getUserConfig();

        return !empty($config['options']['traces_sample_rate'])
            || !empty($config['options']['traces_sampler']);
    }
}
