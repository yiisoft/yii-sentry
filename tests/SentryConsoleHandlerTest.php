<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
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
use Yiisoft\Yii\Sentry\SentryConsoleHandler;
use Yiisoft\Yii\Sentry\Tests\Stub\ErrorHandlerExceptionCommand;
use Yiisoft\Yii\Sentry\Tests\Stub\ExceptionCommand;
use Yiisoft\Yii\Sentry\Tests\Stub\FatalErrorCommand;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;

final class SentryConsoleHandlerTest extends TestCase
{
    public function testHandleWithException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";


        $this->createAndRunAppWithEventHandler($eventKey, ExceptionCommand::class);
        $this->assertTransportHasException(\RuntimeException::class, 'Console exception test.', $eventKey);
    }

    public function testHandleWithFatalError(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunAppWithEventHandler($eventKey, FatalErrorCommand::class);
        $this->assertTransportHasException(\PHPUnit\Framework\Error\Error::class, 'Console fatal error test.', $eventKey);
    }

    public function testHandleWithErrorHandlerException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->createAndRunAppWithEventHandler($eventKey, ErrorHandlerExceptionCommand::class);
        $this->assertCount(0, Transport::$events[$eventKey]);
    }

    private function createAndRunAppWithEventHandler(string $eventKey, string $commandClass): void
    {
        $listeners = (new ListenerCollection())->add(function (ConsoleErrorEvent $event) use ($eventKey) {
            $handler = new SentryConsoleHandler($this->createSentryHub($eventKey));
            $handler->handle($event);
        });
        $provider = new Provider($listeners);
        $dispatcher = new Dispatcher($provider);
        $dispatcher = new SymfonyEventDispatcher($dispatcher);

        $app = new Application();
        $app->setCommandLoader(new CommandLoader(
            new Container(ContainerConfig::create()),
            ['test/command' => $commandClass],
        ));
        $app->setAutoExit(false);
        $app->setDispatcher($dispatcher);
        $app->run(new StringInput('test/command'), new NullOutput());
    }

    public function testHandleWithoutError(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $app = new Application();
        $app->setCommandLoader(new CommandLoader(
            new Container(ContainerConfig::create()),
            ['test/no-error' => ExceptionCommand::class],
        ));
        $app->setAutoExit(false);
        $app->run(new StringInput('test/no-error'), new NullOutput());

        $this->assertArrayNotHasKey($eventKey, Transport::$events);
    }
}
