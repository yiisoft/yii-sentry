<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use Sentry\CheckInStatus;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Yii\Console\Application;
use Yiisoft\Yii\Console\CommandLoader;
use Yiisoft\Yii\Console\SymfonyEventDispatcher;
use Yiisoft\Yii\Sentry\SentryCronMonitor;
use Yiisoft\Yii\Sentry\Tests\Stub\Command;
use Yiisoft\Yii\Sentry\Tests\Stub\ExceptionCommand;
use Yiisoft\Yii\Sentry\Tests\Stub\FailureCommand;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;

final class SentryCronMonitorFeatureTest extends TestCase
{
    public function testSuccessfulCommand(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunApp($eventKey, 'test/command', Command::class, ['test/command' => 'my-monitor']);

        $events = Transport::$events[$eventKey];
        $this->assertCount(2, $events);

        $inProgressCheckIn = $events[0]->getCheckIn();
        $this->assertNotNull($inProgressCheckIn);
        $this->assertSame('my-monitor', $inProgressCheckIn->getMonitorSlug());
        $this->assertSame(CheckInStatus::inProgress(), $inProgressCheckIn->getStatus());
        $this->assertNull($inProgressCheckIn->getDuration());
        $this->assertNull($inProgressCheckIn->getMonitorConfig());

        $okCheckIn = $events[1]->getCheckIn();
        $this->assertNotNull($okCheckIn);
        $this->assertSame('my-monitor', $okCheckIn->getMonitorSlug());
        $this->assertSame(CheckInStatus::ok(), $okCheckIn->getStatus());
        $this->assertIsFloat($okCheckIn->getDuration());
        $this->assertGreaterThanOrEqual(0, $okCheckIn->getDuration());
        $this->assertLessThan(1, $okCheckIn->getDuration());
        $this->assertSame($inProgressCheckIn->getId(), $okCheckIn->getId());
    }

    public function testCommandWithException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunApp($eventKey, 'test/command', ExceptionCommand::class, ['test/command' => 'my-monitor']);

        $events = Transport::$events[$eventKey];
        $this->assertCount(2, $events);

        $this->assertSame(CheckInStatus::inProgress(), $events[0]->getCheckIn()?->getStatus());
        $this->assertSame(CheckInStatus::error(), $events[1]->getCheckIn()?->getStatus());
        $this->assertSame($events[0]->getCheckIn()?->getId(), $events[1]->getCheckIn()?->getId());
    }

    public function testCommandWithFailureExitCode(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunApp($eventKey, 'test/command', FailureCommand::class, ['test/command' => 'my-monitor']);

        $events = Transport::$events[$eventKey];
        $this->assertCount(2, $events);

        $this->assertSame(CheckInStatus::inProgress(), $events[0]->getCheckIn()?->getStatus());
        $this->assertSame(CheckInStatus::error(), $events[1]->getCheckIn()?->getStatus());
        $this->assertSame($events[0]->getCheckIn()?->getId(), $events[1]->getCheckIn()?->getId());
    }

    public function testCommandWithMonitorConfig(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunApp(
            $eventKey,
            'test/command',
            Command::class,
            [
                'test/command' => [
                    'slug' => 'my-monitor',
                    'schedule' => '*/5 * * * *',
                    'timezone' => 'UTC',
                ],
            ]
        );

        $events = Transport::$events[$eventKey];
        $this->assertCount(2, $events);

        $monitorConfig = $events[0]->getCheckIn()?->getMonitorConfig();
        $this->assertNotNull($monitorConfig);
        $this->assertSame('crontab', $monitorConfig->getSchedule()->getType());
        $this->assertSame('*/5 * * * *', $monitorConfig->getSchedule()->getValue());
        $this->assertSame('UTC', $monitorConfig->getTimezone());
    }

    public function testUnmappedCommand(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunApp($eventKey, 'test/unmapped', Command::class, ['test/command' => 'my-monitor']);

        $this->assertCount(0, Transport::$events[$eventKey]);
    }

    private function createAndRunApp(string $eventKey, string $commandName, string $commandClass, array $monitoring): void
    {
        $monitor = new SentryCronMonitor($this->createSentryHub($eventKey), $monitoring);

        $listeners = (new ListenerCollection())
            ->add(static function (ConsoleCommandEvent $event) use ($monitor): void {
                $monitor->handleCommand($event);
            })
            ->add(static function (ConsoleErrorEvent $event) use ($monitor): void {
                $monitor->handleError($event);
            })
            ->add(static function (ConsoleTerminateEvent $event) use ($monitor): void {
                $monitor->handleTerminate($event);
            });
        $provider = new Provider($listeners);
        $dispatcher = new Dispatcher($provider);
        $dispatcher = new SymfonyEventDispatcher($dispatcher);

        $app = new Application();
        $app->setCommandLoader(new CommandLoader(
            new Container(ContainerConfig::create()),
            [$commandName => $commandClass],
        ));
        $app->setAutoExit(false);
        $app->setDispatcher($dispatcher);
        $app->run(new StringInput($commandName), new NullOutput());
    }
}
