<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Yiisoft\Yii\Sentry\SentryMiddleware;

final class SentryMiddlewareTest extends TestCase
{
    public function testProcessWithException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry test.');

        try {
            $this
                ->createSentryMiddleware($eventKey)
                ->process(
                    new ServerRequest(method: 'GET', uri: '/'),
                    $this->createRequestHandlerWithException(),
                );
        } catch (RuntimeException $e) {
            $this->assertCount(1, Transport::$events[$eventKey]);
            $this->assertCount(1, Transport::$events[$eventKey][0]->getExceptions());
            $this->assertEquals(
                'RuntimeException',
                Transport::$events[$eventKey][0]
                    ->getExceptions()[0]
                    ->getType()
            );
            $this->assertEquals(
                'Sentry test.',
                Transport::$events[$eventKey][0]
                    ->getExceptions()[0]
                    ->getValue()
            );

            throw $e;
        }
    }

    public function testProcessWithoutException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $response = $this
            ->createSentryMiddleware($eventKey)
            ->process(
                new ServerRequest(method: 'GET', uri: '/', headers: ['Accept' => ['text/plain;q=2.0']]),
                $this->createRequestHandlerWithoutException(),
            );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertCount(0, Transport::$events[$eventKey]);
    }

    private function createSentryMiddleware(string $eventKey): SentryMiddleware
    {
        $clientBuilder = new ClientBuilder(new Options());
        $clientBuilder->setTransportFactory(new TransportFactory($eventKey));

        $client = $clientBuilder->getClient();

        $hub = new Hub();
        $hub->bindClient($client);

        SentrySdk::setCurrentHub($hub);

        return new SentryMiddleware($hub);
    }

    private function createRequestHandlerWithException(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Sentry test.');
            }
        };
    }

    private function createRequestHandlerWithoutException(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
