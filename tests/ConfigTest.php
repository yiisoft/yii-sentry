<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzleClientAdapter;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use PHPUnit\Framework\TestCase;
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

    public function testEventsConsoleDisabledWithDsnAndHandleConsoleErrors(): void
    {
        $params = [
            'yiisoft/yii-sentry' => [
                'handleConsoleErrors' => false,
                'options' => [
                    'dsn' => null,
                ],
            ],
        ];
        $this->assertEquals([], $this->getEventsConsole($params));
    }

    public function testEventsConsoleDisabledWithDsn(): void
    {
        $params = [
            'yiisoft/yii-sentry' => [
                'handleConsoleErrors' => true,
                'options' => [
                    'dsn' => null,
                ],
            ],
        ];
        $this->assertEquals([], $this->getEventsConsole($params));
    }

    public function testEventsConsoleDisabledWithHandleConsoleErrors(): void
    {
        $params = [
            'yiisoft/yii-sentry' => [
                'handleConsoleErrors' => false,
                'options' => [
                    'dsn' => 'http://username:password@hostname:9090/path',
                ],
            ],
        ];
        $this->assertEquals([], $this->getEventsConsole($params));
    }

    public function testEventsConsoleDisabledWithDefaultHandleConsoleErrorsAndDsn(): void
    {
        $params = [
            'yiisoft/yii-sentry' => [
                'options' => [
                    'dsn' => null,
                ],
            ],
        ];
        $this->assertEquals([], $this->getEventsConsole($params));
    }

    public function testEventsConsoleDisabledWithDefaultHandleConsoleErrors(): void
    {
        $params = [
            'yiisoft/yii-sentry' => [
                'options' => [
                    'dsn' => 'http://username:password@hostname:9090/path',
                ],
            ],
        ];
        $this->assertEquals([], $this->getEventsConsole($params));
    }

    public function testEventsConsoleEnabled(): void
    {
        $params = [
            'yiisoft/yii-sentry' => [
                'handleConsoleErrors' => true,
                'options' => [
                    'dsn' => 'http://username:password@hostname:9090/path',
                ],
            ],
        ];
        $eventsConsole = [
            'Symfony\Component\Console\Event\ConsoleErrorEvent' => [
                ['Yiisoft\Yii\Sentry\SentryConsoleHandler', 'handle'],
            ],
        ];
        $this->assertEquals($eventsConsole, $this->getEventsConsole($params));
    }

    private function createContainer(?array $params = null): Container
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

        return $container;
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
