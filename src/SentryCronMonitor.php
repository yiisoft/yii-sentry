<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use InvalidArgumentException;
use ReflectionMethod;
use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\State\HubInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

use function array_key_exists;
use function assert;
use function date_default_timezone_get;
use function get_debug_type;
use function hrtime;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Sends Sentry cron monitor check-ins for console commands configured in the `monitoring` parameter.
 *
 * The check-in is sent with `in_progress` status when a command starts and with either `ok` or `error`
 * status when it terminates or fails with an exception.
 *
 * @see https://docs.sentry.io/platforms/php/crons/
 */
final class SentryCronMonitor
{
    private string $slug = '';
    private ?string $checkInId = null;
    private ?int $startedAt = null;
    private bool $finished = false;

    /**
     * @param array $monitoring Map of console command name to a monitor slug or monitor configuration.
     *
     * @psalm-param array<string, mixed> $monitoring
     */
    public function __construct(
        private readonly HubInterface $hub,
        private readonly array $monitoring = [],
    ) {
    }

    public function handleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        assert($command !== null);

        $commandName = $command->getName();

        if ($commandName === null || !array_key_exists($commandName, $this->monitoring)) {
            return;
        }

        $config = $this->monitoring[$commandName];

        if (!is_string($config) && !is_array($config)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Sentry monitor configuration for the "%s" console command must be a string or an array, got %s.',
                    $commandName,
                    get_debug_type($config),
                )
            );
        }

        $this->slug = is_string($config) ? $config : $this->extractSlug($config, $commandName);
        $this->checkInId = $this->hub->captureCheckIn(
            $this->slug,
            CheckInStatus::inProgress(),
            monitorConfig: is_array($config) ? $this->createMonitorConfig($config) : null,
        );
        $this->startedAt = hrtime(true);
        $this->finished = false;
    }

    public function handleError(ConsoleErrorEvent $event): void
    {
        $this->captureFinalCheckIn(CheckInStatus::error());
    }

    public function handleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->captureFinalCheckIn(
            $event->getExitCode() === 0 ? CheckInStatus::ok() : CheckInStatus::error()
        );
    }

    private function captureFinalCheckIn(CheckInStatus $status): void
    {
        if ($this->finished || $this->startedAt === null) {
            return;
        }

        $this->finished = true;

        $this->hub->captureCheckIn(
            $this->slug,
            $status,
            duration: (hrtime(true) - $this->startedAt) / 1_000_000_000,
            checkInId: $this->checkInId,
        );
    }

    /**
     * @psalm-param array<array-key, mixed> $config
     */
    private function extractSlug(array $config, string $commandName): string
    {
        $slug = $config['slug'] ?? null;

        if (!is_string($slug) || $slug === '') {
            throw new InvalidArgumentException(
                sprintf('Sentry monitor slug for the "%s" console command is not configured.', $commandName)
            );
        }

        return $slug;
    }

    /**
     * @psalm-param array<array-key, mixed> $config
     */
    private function createMonitorConfig(array $config): ?MonitorConfig
    {
        if (!isset($config['schedule'])) {
            return null;
        }

        if (!is_string($config['schedule']) || $config['schedule'] === '') {
            throw new InvalidArgumentException(
                sprintf('Sentry monitor schedule must be a non-empty string, got %s.', get_debug_type($config['schedule']))
            );
        }

        $checkinMargin = $config['checkinMargin'] ?? null;
        $maxRuntime = $config['maxRuntime'] ?? null;
        $timezone = $config['timezone'] ?? null;
        $failureIssueThreshold = $config['failureIssueThreshold'] ?? null;
        $recoveryThreshold = $config['recoveryThreshold'] ?? null;

        $arguments = [
            'schedule' => MonitorSchedule::crontab($config['schedule']),
            'checkinMargin' => is_int($checkinMargin) ? $checkinMargin : null,
            'maxRuntime' => is_int($maxRuntime) ? $maxRuntime : null,
            'timezone' => is_string($timezone) && $timezone !== '' ? $timezone : date_default_timezone_get(),
        ];

        // These `MonitorConfig` parameters were added in sentry/sentry 4.4.
        if (self::monitorConfigSupportsThresholds()) {
            $arguments['failureIssueThreshold'] = is_int($failureIssueThreshold) ? $failureIssueThreshold : null;
            $arguments['recoveryThreshold'] = is_int($recoveryThreshold) ? $recoveryThreshold : null;
        }

        return new MonitorConfig(...$arguments);
    }

    private static function monitorConfigSupportsThresholds(): bool
    {
        static $supports;

        if ($supports !== null) {
            return $supports;
        }

        foreach ((new ReflectionMethod(MonitorConfig::class, '__construct'))->getParameters() as $parameter) {
            if ($parameter->getName() === 'failureIssueThreshold') {
                return $supports = true;
            }
        }

        return $supports = false;
    }
}
