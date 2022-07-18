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
use Yiisoft\Yii\Sentry\Tests\Stub\ErrorCommand;

final class SentryConsoleHandlerTest extends TestCase
{
    public function testHandleWithException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

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
            ['test/error' => ErrorCommand::class],
        ));
        $app->setAutoExit(false);
        $app->setDispatcher($dispatcher);
        $app->run(new StringInput('test/error'), new NullOutput());

        $this->assertTransportHasException('RuntimeException', 'Sentry console test.', $eventKey);
    }
}
