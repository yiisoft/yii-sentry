<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use Error;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Yiisoft\Yii\Sentry\SentryMiddleware;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;

final class SentryMiddlewareTest extends TestCase
{
    public function testProcessWithException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Exception test.');

        try {
            $middleware = new SentryMiddleware($this->createSentryHub($eventKey));
            $middleware->process(
                new ServerRequest(method: 'GET', uri: '/'),
                $this->createRequestHandlerWithException(),
            );
        } catch (RuntimeException $e) {
            $this->assertTransportHasException('RuntimeException', 'Exception test.', $eventKey);

            throw $e;
        }
    }

    public function testProcessWithFatalError(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->expectError();
        $this->expectExceptionMessage('Fatal error test.');

        try {
            $middleware = new SentryMiddleware($this->createSentryHub($eventKey));
            $middleware->process(
                new ServerRequest(method: 'GET', uri: '/'),
                $this->createRequestHandlerWithFatalError(),
            );
        } catch (Error $e) {
            $this->assertTransportHasException('PHPUnit\Framework\Error\Error', 'Fatal error test.', $eventKey);

            throw $e;
        }
    }

    public function testProcessWithoutException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $middleware = new SentryMiddleware($this->createSentryHub($eventKey));
        $response = $middleware->process(
            new ServerRequest(method: 'GET', uri: '/', headers: ['Accept' => ['text/plain;q=2.0']]),
            $this->createRequestHandlerWithoutException(),
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertCount(0, Transport::$events[$eventKey]);
    }

    private function createRequestHandlerWithException(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Exception test.');
            }
        };
    }

    private function createRequestHandlerWithFatalError(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                trigger_error('Fatal error test.', E_USER_ERROR);
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
