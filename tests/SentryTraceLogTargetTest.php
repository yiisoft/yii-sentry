<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\Event;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;
use Yiisoft\Yii\Sentry\Tracing\SentryTraceMiddleware;

class SentryTraceLogTargetTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $self = new self();
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";
        $self->createSentryHub($eventKey);
        $self->getLogger()->flush(true);//drop init integration debug messages breadcrumb
    }

    public function testAutotrace(): void
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";
        $hub = $this->createSentryHub($eventKey, [
            'options' => [
                'send_default_pii' => true,
                'traces_sample_rate' => 1.0,
            ],
            'tracing' => [
                'default_integrations' => true,
            ],
        ]);

        $middleware = new SentryTraceMiddleware($hub, new CurrentRoute());

        $innerHandler = function () {
            $log = $this->getLogger();

            $log->info('some info', [
                'time' => 1.0,
                'elapsed' => .01,
                'memory' => 5 * (1024 * 1024),
            ]);
            $log->log('debug', 'some debug', [
                'category' => 'my category',
                'elapsed' => .01,
                'memory' => 5 * (1024 * 1024),
                'time' => 2.0,
            ]);
        };

        $middleware->process(
            new ServerRequest(method: 'GET', uri: '/', headers: ['Accept' => ['text/plain;q=2.0']]),
            $this->createRequestHandlerWithoutException($innerHandler),
        );

        $this->getLogger()->flush(true);
        $middleware->terminate();

        /** @var Event $event */
        $event = Transport::$events[$eventKey][0];
        $spanApp = $event->getSpans()[0];
        $spanInfo = $event->getSpans()[1];
        $spanDebug = $event->getSpans()[2];

        $this->assertCount(1, Transport::$events[$eventKey]);
        $this->assertEquals('http.server', $spanApp->getTransaction()->getOp());
        $this->assertEquals('app.handle', $spanApp->getOp());
        $this->assertEquals('some info', $spanInfo->getDescription());
        $this->assertEquals('some debug', $spanDebug->getDescription());
        $this->assertEquals('my category', $spanDebug->getOp());
    }

    private function createRequestHandlerWithoutException($innerHandler): RequestHandlerInterface
    {
        return new class ($innerHandler) implements RequestHandlerInterface {
            public function __construct(public $innerHandler)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $handler = $this->innerHandler;
                $handler($request);
                return new Response();
            }
        };
    }
}
