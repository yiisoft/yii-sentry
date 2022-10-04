<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use Couchbase\BaseException;
use Error;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\Error\Error as PHPUnitError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sentry\Event;
use Yiisoft\ErrorHandler\Exception\ErrorException;
use Yiisoft\Yii\Sentry\SentryMiddleware;
use Yiisoft\Yii\Sentry\Tests\Stub\ContextableException;
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

        $this->expectError();
        $this->expectExceptionMessage('Fatal error test.');

        try {
            $middleware = new SentryMiddleware($this->createSentryHub($eventKey));
            $middleware->process(
                new ServerRequest(method: 'GET', uri: '/'),
                $this->createRequestHandlerWithFatalError(),
            );
        } catch (Error $e) {
            $this->assertTransportHasException(PHPUnitError::class, 'Fatal error test.', $eventKey);

            throw $e;
        }
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

    public function testProcessWithContextableException(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";

        $this->expectException(ContextableException::class);
        $this->expectExceptionMessage('Error handler exception test with context.');
        $hub = $this->createSentryHub($eventKey, [
            'options' => [
                'send_default_pii' => true,
                'traces_sample_rate' => 1.0,
            ],
            'tracing' => [
                'default_integrations' => true,
            ],
        ]);
        $middleware = new SentryMiddleware($hub);
        try {
            $middleware->process(
                new ServerRequest(method: 'GET', uri: '/'),
                $this->createRequestHandlerWithContextableErrorHandlerException()
            );
        } catch (ContextableException $e) {
            $this->assertCount(1, Transport::$events[$eventKey]);
            /** @var Event $event */
            $event = Transport::$events[$eventKey][0];
            $this->assertEquals(['exception_context' => [['key'=>'context value']]], $event->getExtra());

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

    private function createRequestHandlerWithContextableErrorHandlerException(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw (new ContextableException('Error handler exception test with context.'))
                    ->addContext(['key'=>'context value']);
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
