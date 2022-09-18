<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Yiisoft\Di\CompositeContainer;
use Yiisoft\Log\Logger;
use Yiisoft\Yii\Sentry\HubBootstrapper;
use Yiisoft\Yii\Sentry\SentryBreadcrumbLogTarget;
use Yiisoft\Yii\Sentry\SentryLogAdapter;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;
use Yiisoft\Yii\Sentry\Tests\Stub\TransportFactory;
use Yiisoft\Yii\Sentry\Tracing\SentryTraceLogTarget;
use Yiisoft\Yii\Sentry\YiiSentryConfig;

abstract class TestCase extends BaseTestCase
{
    protected ?LoggerInterface $logger = null;

    protected function createSentryHub(string $eventKey, array $overrideParams = []): HubInterface
    {
        $hub = new Hub();
        $params = $this->getParams()['yiisoft/yii-sentry'];
        $params = array_merge($params, [
            'handleConsoleErrors' => true,
            'log_level'           => 'warning',
            'tracing'             => [
                // Indicates if the tracing integrations supplied by Sentry should be loaded
                'default_integrations' => true,
            ],
        ], $overrideParams);
        $config = new YiiSentryConfig($params);

        $handler = new SentryLogAdapter($hub, $config);
        $logTarget = new SentryBreadcrumbLogTarget($handler);
        $traceTarget = new SentryTraceLogTarget();
        $logger = new Logger([$logTarget, $traceTarget]);
        $this->logger = $logger;

        $bootstrapper = new HubBootstrapper(
            options: new Options($config->getOptions()),
            configuration: $config,
            transportFactory: new TransportFactory($eventKey),
            logger: $logger,
            hub: $hub,
            container: new CompositeContainer()
        );
        $bootstrapper->bootstrap();
        return $hub;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? throw new \Exception("need logger");
    }

    protected function assertTransportHasException(string $name, string $message, string $eventKey): void
    {
        $this->assertCount(1, Transport::$events[$eventKey]);
        $this->assertCount(1, Transport::$events[$eventKey][0]->getExceptions());
        $this->assertEquals(
            $name,
            Transport::$events[$eventKey][0]
                ->getExceptions()[0]
                ->getType()
        );
        $this->assertEquals(
            $message,
            Transport::$events[$eventKey][0]
                ->getExceptions()[0]
                ->getValue()
        );
    }

    private function getParams(): array
    {
        return require dirname(__DIR__) . '/config/params.php';
    }
}
