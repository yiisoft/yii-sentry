<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzleClientAdapter;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use HttpSoft\Message\RequestFactory;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Sentry\Client;
use Sentry\SentrySdk;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

final class ConfigTest extends TestCase
{
    public function testDsnIsNotSet(): void
    {
        $property = new ReflectionProperty(Client::class, 'transport');
        $property->setAccessible(true);

        $this->createContainer();
        $hub = SentrySdk::getCurrentHub();

        $client = $hub->getClient();
        $this->assertNull($client->getOptions()->getDsn());

        $transport = $property->getValue($client);
        $this->assertInstanceOf(NullTransport::class, $transport);
    }

    public function testDsnSet(): void
    {
        $dsn = 'http://username:password@hostname:9090/path';
        $environment = 'test environment';
        $this->createContainer([
            'yiisoft/yii-sentry' => [
                'options' => [
                    'dsn' => $dsn,
                    'environment' => $environment,
                ],
            ],
        ]);

        $property = new ReflectionProperty(Client::class, 'transport');
        $property->setAccessible(true);

        $hub = SentrySdk::getCurrentHub();

        $client = $hub->getClient();
        $this->assertSame($dsn, (string) $client->getOptions()->getDsn());
        $this->assertSame($environment, $client->getOptions()->getEnvironment());

        $transport = $property->getValue($client);
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
                    'Symfony\Component\Console\Event\ConsoleErrorEvent' => [
                        ['Yiisoft\Yii\Sentry\SentryConsoleHandler', 'handle'],
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

    private function createContainer(?array $params = null): void
    {
        $container = new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getCommonDefinitions($params)
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

    private function getCommonDefinitions(?array $params = null): array
    {
        if ($params === null) {
            $params = $this->getParams();
        }

        $definitions = require dirname(__DIR__) . '/config/common.php';
        $additionalDefinitions = [
            // HTTP Factories
            StreamFactoryInterface::class => StreamFactory::class,
            RequestFactoryInterface::class => RequestFactory::class,
            LoggerInterface::class => NullLogger::class,
            UriFactoryInterface::class => UriFactory::class,
            ResponseFactoryInterface::class => ResponseFactory::class,
            // HTTP Client
            HttpClient::class => GuzzleClient::class,
            HttpAsyncClient::class => [
                'class' => GuzzleClientAdapter::class,
                '__construct()' => [
                    Reference::to(HttpClient::class),
                ],
            ],
        ];

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
}
