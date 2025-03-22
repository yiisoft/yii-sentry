<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\SentrySdk;
use Sentry\Transport\HttpTransport;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Yii\Sentry\SentryConsoleHandler;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;

final class ConfigTest extends TestCase
{
    public function testDsnIsNotSet(): void
    {
        $this->createContainer();
        $hub = SentrySdk::getCurrentHub();

        $client = $hub->getClient();
        $this->assertNull($client->getOptions()->getDsn());

        $transport = $client->getTransport();
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testDsnSet(): void
    {
        $dsn = 'http://publicKey@hostname:9090/path';
        $environment = 'test environment';
        $this->createContainer([
            'yiisoft/yii-sentry' => [
                'options' => [
                    'dsn' => $dsn,
                    'environment' => $environment,
                ],
            ],
        ]);

        $hub = SentrySdk::getCurrentHub();

        $client = $hub->getClient();
        $this->assertSame($dsn, (string) $client->getOptions()->getDsn());
        $this->assertSame($environment, $client->getOptions()->getEnvironment());

        $transport = $client->getTransport();
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function eventsConsoleDataProvider(): array
    {
        return [
            'disabledWithDsnAndHandleConsoleErrors' => [
                [
                    'yiisoft/yii-sentry' => [
                        'handleConsoleErrors' => false,
                        'options' => [
                            'dsn' => null,
                        ],
                    ],
                ],
                [],
            ],
            'disabledWithDsn' => [
                [
                    'yiisoft/yii-sentry' => [
                        'handleConsoleErrors' => true,
                        'options' => [
                            'dsn' => null,
                        ],
                    ],
                ],
                [],
            ],
            'disabledWithHandleConsoleErrors' => [
                [
                    'yiisoft/yii-sentry' => [
                        'handleConsoleErrors' => false,
                        'options' => [
                            'dsn' => 'http://username:password@hostname:9090/path',
                        ],
                    ],
                ],
                [],
            ],
            'disabledWithDefaultHandleConsoleErrorsAndDsn' => [
                [
                    'yiisoft/yii-sentry' => [
                        'options' => [
                            'dsn' => null,
                        ],
                    ],
                ],
                [],
            ],
            'disabledWithDefaultHandleConsoleErrors' => [
                [
                    'yiisoft/yii-sentry' => [
                        'options' => [
                            'dsn' => 'http://username:password@hostname:9090/path',
                        ],
                    ],
                ],
                [],
            ],
            'enabled' => [
                [
                    'yiisoft/yii-sentry' => [
                        'handleConsoleErrors' => true,
                        'options' => [
                            'dsn' => 'http://username:password@hostname:9090/path',
                        ],
                    ],
                ],
                [
                    ConsoleErrorEvent::class => [
                        [SentryConsoleHandler::class, 'handle'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider eventsConsoleDataProvider
     */
    public function testEventsConsole(array $params, $expectedEventsConsole): void
    {
        $this->assertEquals($expectedEventsConsole, $this->getEventsConsole($params));
    }

    private function createContainer(?array $params = null, array $additionalDefinitions = []): void
    {
        $container = new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getContainerDefinitions($params, $additionalDefinitions)
            )
        );

        $bootstrapList = $this->getBootstrapList();
        $this->assertCount(1, $bootstrapList);

        $callback = $bootstrapList[0];
        $callback($container);
    }

    private function getBootstrapList(): array
    {
        return require dirname(__DIR__) . '/config/bootstrap.php';
    }

    private function getContainerDefinitions(?array $params = null, array $additionalDefinitions = []): array
    {
        if ($params === null) {
            $params = $this->getParams();
        }

        $definitions = require dirname(__DIR__) . '/config/di.php';

        return array_merge($definitions, $additionalDefinitions);
    }

    private function getEventsConsole(array $params): array
    {
        return require dirname(__DIR__) . '/config/events-console.php';
    }

    private function getParams(): array
    {
        return require dirname(__DIR__) . '/config/params.php';
    }

    public function testLoggerDi(): void
    {
        $expectedLogger = new NullLogger();
        $this->createContainer(
            [
                'yiisoft/yii-sentry' => [
                    'options' => [],
                ],
            ],
            [LoggerInterface::class => static fn() => $expectedLogger]
        );

        $logger = SentrySdk::getCurrentHub()->getClient()->getLogger();

        $this->assertSame($expectedLogger, $logger);
    }

    public function testLoggerOption(): void
    {
        $expectedLogger = new NullLogger();
        $this->createContainer([
            'yiisoft/yii-sentry' => [
                'options' => [
                    'logger' => $expectedLogger,
                ],
            ],
        ]);

        $logger = SentrySdk::getCurrentHub()->getClient()->getLogger();

        $this->assertSame($expectedLogger, $logger);
    }

    public function testTransportOption(): void
    {
        $expectedTransport = new Transport('transport from di');
        $this->createContainer([
            'yiisoft/yii-sentry' => [
                'options' => [
                    'transport' => $expectedTransport,
                ],
            ],
        ]);

        $transport = SentrySdk::getCurrentHub()->getClient()->getTransport();

        $this->assertSame($expectedTransport, $transport);
    }
}
