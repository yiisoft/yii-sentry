<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;

abstract class TestCase extends BaseTestCase
{
    protected function createSentryHub(string $eventKey): HubInterface
    {
        $options = $this->getParams()['yiisoft/yii-sentry']['options'];
        $clientBuilder = new ClientBuilder(new Options($options));
        $clientBuilder->setTransport(new Transport($eventKey));

        $client = $clientBuilder->getClient();

        $hub = new Hub();
        $hub->bindClient($client);

        SentrySdk::setCurrentHub($hub);

        return $hub;
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
