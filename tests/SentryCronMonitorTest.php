<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\State\HubInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Yiisoft\Yii\Sentry\SentryCronMonitor;
use Yiisoft\Yii\Sentry\Tests\Stub\Command;

final class SentryCronMonitorTest extends TestCase
{
    private const CHECK_IN_ID = 'test-check-in-id';

    private array $calls = [];

    public function testInProgressCheckInWithStringConfig(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent('test/command'));

        $this->assertCount(1, $this->calls);
        [$slug, $status, $duration, $monitorConfig, $checkInId] = $this->calls[0];
        $this->assertSame('my-monitor', $slug);
        $this->assertSame(CheckInStatus::inProgress(), $status);
        $this->assertNull($duration);
        $this->assertNull($monitorConfig);
        $this->assertNull($checkInId);
    }

    public function testInProgressCheckInWithFullConfig(): void
    {
        $monitor = new SentryCronMonitor(
            $this->createHub(),
            [
                'test/command' => [
                    'slug' => 'my-monitor',
                    'schedule' => '0 3 * * *',
                    'timezone' => 'Europe/Skopje',
                    'checkinMargin' => 5,
                    'maxRuntime' => 30,
                    'failureIssueThreshold' => 2,
                    'recoveryThreshold' => 3,
                ],
            ]
        );

        $monitor->handleCommand($this->createCommandEvent('test/command'));

        $this->assertCount(1, $this->calls);
        $monitorConfig = $this->calls[0][3];
        $this->assertInstanceOf(MonitorConfig::class, $monitorConfig);
        $this->assertSame('crontab', $monitorConfig->getSchedule()->getType());
        $this->assertSame('0 3 * * *', $monitorConfig->getSchedule()->getValue());
        $this->assertSame('Europe/Skopje', $monitorConfig->getTimezone());
        $this->assertSame(5, $monitorConfig->getCheckinMargin());
        $this->assertSame(30, $monitorConfig->getMaxRuntime());
        $this->assertSame(2, $monitorConfig->getFailureRecoveryThreshold());
        $this->assertSame(3, $monitorConfig->getRecoveryThreshold());
    }

    public function testInProgressCheckInWithConfigWithoutSchedule(): void
    {
        $monitor = new SentryCronMonitor(
            $this->createHub(),
            ['test/command' => ['slug' => 'my-monitor']]
        );

        $monitor->handleCommand($this->createCommandEvent('test/command'));

        $this->assertCount(1, $this->calls);
        $this->assertSame('my-monitor', $this->calls[0][0]);
        $this->assertNull($this->calls[0][3]);
    }

    public function testTimezoneDefaultsToPhpDefault(): void
    {
        $monitor = new SentryCronMonitor(
            $this->createHub(),
            ['test/command' => ['slug' => 'my-monitor', 'schedule' => '* * * * *']]
        );

        $monitor->handleCommand($this->createCommandEvent('test/command'));

        $monitorConfig = $this->calls[0][3];
        $this->assertInstanceOf(MonitorConfig::class, $monitorConfig);
        $this->assertSame(date_default_timezone_get(), $monitorConfig->getTimezone());
    }

    public static function invalidConfigProvider(): array
    {
        return [
            'missing slug' => [[]],
            'empty slug' => [['slug' => '']],
            'non-string slug' => [['slug' => 42]],
        ];
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testInvalidConfigThrows(array $config): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => $config]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sentry monitor slug for the "test/command" console command is not configured.');

        $monitor->handleCommand($this->createCommandEvent('test/command'));
    }

    public static function invalidConfigTypeProvider(): array
    {
        return [
            'int' => [42],
            'bool' => [true],
            'null' => [null],
        ];
    }

    /**
     * @dataProvider invalidConfigTypeProvider
     */
    public function testInvalidConfigTypeThrows(mixed $config): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => $config]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sentry monitor configuration for the "test/command" console command must be a string or an array'
        );

        $monitor->handleCommand($this->createCommandEvent('test/command'));
    }

    public static function invalidScheduleProvider(): array
    {
        return [
            'empty string' => [''],
            'non-string' => [42],
        ];
    }

    /**
     * @dataProvider invalidScheduleProvider
     */
    public function testInvalidScheduleThrows(mixed $schedule): void
    {
        $monitor = new SentryCronMonitor(
            $this->createHub(),
            ['test/command' => ['slug' => 'my-monitor', 'schedule' => $schedule]]
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sentry monitor schedule must be a non-empty string');

        $monitor->handleCommand($this->createCommandEvent('test/command'));
    }

    public function testOkCheckInOnTerminate(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent('test/command'));
        $monitor->handleTerminate($this->createTerminateEvent(0));

        $this->assertCount(2, $this->calls);
        [$slug, $status, $duration, $monitorConfig, $checkInId] = $this->calls[1];
        $this->assertSame('my-monitor', $slug);
        $this->assertSame(CheckInStatus::ok(), $status);
        $this->assertIsFloat($duration);
        $this->assertGreaterThanOrEqual(0, $duration);
        $this->assertLessThan(1, $duration);
        $this->assertNull($monitorConfig);
        $this->assertSame(self::CHECK_IN_ID, $checkInId);
    }

    public function testErrorCheckInOnErrorEvent(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent('test/command'));
        $monitor->handleError($this->createErrorEvent());

        $this->assertCount(2, $this->calls);
        $this->assertSame(CheckInStatus::error(), $this->calls[1][1]);
        $this->assertSame(self::CHECK_IN_ID, $this->calls[1][4]);
    }

    public function testErrorCheckInIsNotSentTwiceOnTerminateAfterError(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent('test/command'));
        $monitor->handleError($this->createErrorEvent());
        $monitor->handleTerminate($this->createTerminateEvent(1));

        $this->assertCount(2, $this->calls);
    }

    public function testErrorCheckInOnNonZeroExitCode(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent('test/command'));
        $monitor->handleTerminate($this->createTerminateEvent(1));

        $this->assertCount(2, $this->calls);
        $this->assertSame(CheckInStatus::error(), $this->calls[1][1]);
    }

    public function testUnmappedCommandIsIgnored(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent('other/command'));
        $monitor->handleTerminate($this->createTerminateEvent(0));

        $this->assertCount(0, $this->calls);
    }

    public function testUnnamedCommandIsIgnored(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleCommand($this->createCommandEvent(null));
        $monitor->handleTerminate($this->createTerminateEvent(0));

        $this->assertCount(0, $this->calls);
    }

    public function testTerminateWithoutCommandStartIsIgnored(): void
    {
        $monitor = new SentryCronMonitor($this->createHub(), ['test/command' => 'my-monitor']);

        $monitor->handleTerminate($this->createTerminateEvent(0));

        $this->assertCount(0, $this->calls);
    }

    private function createHub(): HubInterface
    {
        $this->calls = [];

        $hub = $this->createMock(HubInterface::class);
        $hub
            ->method('captureCheckIn')
            ->willReturnCallback(function (...$args): string {
                $this->calls[] = $args;
                return self::CHECK_IN_ID;
            });

        return $hub;
    }

    private function createCommandEvent(?string $commandName): ConsoleCommandEvent
    {
        $command = new Command();
        if ($commandName !== null) {
            $command->setName($commandName);
        }

        return new ConsoleCommandEvent($command, new StringInput(''), new NullOutput());
    }

    private function createTerminateEvent(int $exitCode): ConsoleTerminateEvent
    {
        $command = (new Command())->setName('test/command');

        return new ConsoleTerminateEvent($command, new StringInput(''), new NullOutput(), $exitCode);
    }

    private function createErrorEvent(): ConsoleErrorEvent
    {
        return new ConsoleErrorEvent(new StringInput(''), new NullOutput(), new RuntimeException('test'));
    }
}
