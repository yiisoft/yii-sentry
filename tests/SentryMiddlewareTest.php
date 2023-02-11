<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\Error\Error as PHPUnitError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Yiisoft\ErrorHandler\Exception\ErrorException;
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
            $this->assertTransportHasException(RuntimeException::class, 'Exception test.', $eventKey);

            throw $e;
        }
    }

    public function testProcessWithFatalError(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $middleware = new SentryMiddleware($this->createSentryHub($eventKey));
        $serverRequest = new ServerRequest(method: 'GET', uri: '/');
        $requestHandler = $this->createRequestHandlerWithFatalError();

        try {
            $middleware->process($serverRequest, $requestHandler);
            $exception = null;
        } catch (Throwable $exception) {
        }

        $this->assertSame('Fatal error test.', $exception->getMessage());
        $this->assertTransportHasException(PHPUnitError::class, 'Fatal error test.', $eventKey);
    }

    public function testProcessWithErrorHandlerException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Error handler exception test.');

        try {
            $middleware = new SentryMiddleware($this->createSentryHub($eventKey));
            $middleware->process(
                new ServerRequest(method: 'GET', uri: '/'),
                $this->createRequestHandlerWithErrorHandlerException()
            );
        } catch (ErrorException $e) {
            $this->assertCount(0, Transport::$events[$eventKey]);

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

    private function createRequestHandlerWithErrorHandlerException(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new ErrorException('Error handler exception test.');
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
