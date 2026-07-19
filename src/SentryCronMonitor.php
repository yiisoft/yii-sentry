<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use InvalidArgumentException;
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
use function is_array;
use function is_string;
use function microtime;
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
    private ?float $startedAt = null;
    private bool $finished = false;

    /**
     * @param array $monitoring Map of console command name to a monitor slug or monitor configuration.
     *
     * @psalm-param array<string, string|array{slug:string, schedule?:string, timezone?:string, checkinMargin?:int, maxRuntime?:int, failureIssueThreshold?:int, recoveryThreshold?:int}> $monitoring
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

        $this->slug = is_string($config) ? $config : $this->extractSlug($config, $commandName);
        $this->checkInId = $this->hub->captureCheckIn(
            $this->slug,
            CheckInStatus::inProgress(),
            monitorConfig: is_array($config) ? $this->createMonitorConfig($config) : null,
        );
        $this->startedAt = microtime(true);
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
            duration: microtime(true) - $this->startedAt,
            checkInId: $this->checkInId,
        );
    }

    /**
     * @psalm-param array{slug?:string, schedule?:string, timezone?:string, checkinMargin?:int, maxRuntime?:int, failureIssueThreshold?:int, recoveryThreshold?:int} $config
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
     * @psalm-param array{slug?:string, schedule?:string, timezone?:string, checkinMargin?:int, maxRuntime?:int, failureIssueThreshold?:int, recoveryThreshold?:int} $config
     */
    private function createMonitorConfig(array $config): ?MonitorConfig
    {
        if (!isset($config['schedule'])) {
            return null;
        }

        return new MonitorConfig(
            MonitorSchedule::crontab($config['schedule']),
            checkinMargin: $config['checkinMargin'] ?? null,
            maxRuntime: $config['maxRuntime'] ?? null,
            timezone: $config['timezone'] ?? date_default_timezone_get(),
            failureIssueThreshold: $config['failureIssueThreshold'] ?? null,
            recoveryThreshold: $config['recoveryThreshold'] ?? null,
        );
    }
}
