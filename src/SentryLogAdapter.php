<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use DateTimeInterface;
use Psr\Log\LogLevel;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;
use Yiisoft\Yii\Sentry\Integration\Integration;

final class SentryLogAdapter
{
    /**
     * @psalm-suppress PropertyNotSetInConstructor
     *
     * @var string|null the current application environment (staging|preprod|prod)
     */
    protected ?string $environment = null;

    /**
     * @psalm-suppress PropertyNotSetInConstructor
     *
     * @var string|null should represent the current version of the calling
     *             software. Can be any string (git commit, version number)
     */
    protected ?string $release = null;

    protected string $minLevel;

    protected array $levels
        = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

    public function __construct(
        private HubInterface $hub,
        YiiSentryConfig $config,
    ) {
        $this->minLevel = $config->getLogLevel() ?? LogLevel::ERROR;
    }

    /**
     * @suppress PhanTypeMismatchArgument
     */
    public function log(string $level, string $message, array $context): void
    {
        /** @psalm-suppress MixedAssignment */
        $exception = $context['exception'] ?? $context['throwable'] ?? null;
        unset($context['exception'], $context['throwable']);

        $this->hub->withScope(
            function (Scope $scope) use (
                $exception,
                $level,
                $message,
                $context,
            ) {
                if (!empty($context['extra'])) {
                    /** @psalm-suppress MixedAssignment */
                    foreach ($context['extra'] as $key => $tag) {
                        $scope->setExtra((string)$key, $tag);
                    }
                    unset($context['extra']);
                }

                if (!empty($context['tags'])) {
                    /** @psalm-suppress MixedAssignment */
                    foreach ($context['tags'] as $key => $tag) {
                        $scope->setTag((string)$key, (string)$tag);
                    }
                    unset($context['tags']);
                }

                if (is_array($context['fingerprint'] ?? null)) {
                    $scope->setFingerprint(
                        $this->formatFingerPrint((array)$context['fingerprint'])
                    );
                    unset($context['fingerprint']);
                }

                if (is_array($context['user'] ?? null)) {
                    $scope->setUser(
                        $this->formatUser((array)$context['user'])
                    );
                    unset($context['user']);
                }

                $logger = !empty($context['logger']) ? (string)$context['logger']
                    : 'default logger';
                unset($context['logger']);

                if (!empty($context)) {
                    $scope->setExtra('log_context', $context);
                }

                $scope->addEventProcessor(
                    function (Event $event) use ($logger, $level, $context) {
                        $event->setLevel($this->getLogLevel($level));
                        $event->setLogger($logger);
                        if (
                            !empty($this->environment)
                            && !$event->getEnvironment()
                        ) {
                            $event->setEnvironment($this->environment);
                        }

                        if (!empty($this->release) && !$event->getRelease()) {
                            $event->setRelease($this->release);
                        }

                        if (
                            isset($context['datetime'])
                            && $context['datetime'] instanceof DateTimeInterface
                        ) {
                            $event->setTimestamp(
                                $context['datetime']->getTimestamp()
                            );
                        }

                        return $event;
                    }
                );
                if ($this->allowLevel($level)) {
                    if ($exception instanceof Throwable) {
                        $this->hub->captureException($exception);
                    } else {
                        $this->hub->captureMessage($message);
                    }
                }
            }
        );
    }

    /**
     * @return string[]
     */
    private function formatFingerPrint(array $fingerprint): array
    {
        $result = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($fingerprint as $key => $value) {
            $result[$key] = (is_string($value) || is_numeric($value))
                ? (string)$value
                : '<wrong value>';
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(array $user): array
    {
        $result = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($user as $key => $value) {
            $result[(string)$key] = $value;
        }

        return $result;
    }

    /**
     * Translates Monolog log levels to Sentry Severity.
     */
    protected function getLogLevel(string $logLevel): Severity
    {
        return match ($logLevel) {
            LogLevel::DEBUG => Severity::debug(),
            LogLevel::NOTICE, LogLevel::INFO => Severity::info(),
            LogLevel::WARNING => Severity::warning(),
            LogLevel::ALERT, LogLevel::EMERGENCY, LogLevel::CRITICAL => Severity::fatal(),
            default => Severity::error(),
        };
    }

    private function allowLevel(string $level): bool
    {
        return ($this->levels[$level] ?? 0)
            <= ($this->levels[$this->minLevel] ?? 0);
    }

    public function breadcrumb(string $level, string $message, array $context): void
    {
        $category = (string)($context['category'] ?? 'log');
        $time = (float)($context['time'] ?? microtime(true));
        unset($context['category'], $context['time']);

        if (
            array_key_exists('trace', $context)
            && empty($context['trace'])
        ) {
            unset($context['trace']);
        }
        if (!empty($context['memory']) && is_numeric($context['memory'])) {
            $context['memory'] = round(
                ((float)$context['memory'] / (1024 * 1024)),
                2
            ) . 'MB';
        }
        $formattedContext = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($context as $key => $value) {
            $formattedContext[(string)$key] = $value;
        }
        Integration::addBreadcrumb(
            new Breadcrumb(
                Integration::logLevelToBreadcrumbLevel(
                    $level
                ),
                Breadcrumb::TYPE_DEFAULT,
                $category,
                $message,
                $formattedContext,
                $time
            )
        );
    }

    /**
     * Set the release.
     *
     * @param string $value
     */
    public function setRelease($value): self
    {
        $this->release = $value;

        return $this;
    }

    /**
     * Set the current application environment.
     */
    public function setEnvironment(?string $value): self
    {
        $this->environment = $value;

        return $this;
    }
}
