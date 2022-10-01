<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Yiisoft\Yii\Sentry\Integration;
use Psr\Http\Server\MiddlewareInterface;
use Sentry\Tracing\SpanContext;
use Yiisoft\Middleware\Dispatcher\Event\AfterMiddleware;
use Yiisoft\Middleware\Dispatcher\Event\BeforeMiddleware;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

final class EventWebTraceHandler
{
    private ?float $prevTime = null;

    private ?string $prevClass = null;

    private ?bool $isFirstPopFromMiddlewareStack = true;

    public function __construct(private SentryTraceMiddleware $traceMiddleware)
    {
    }

    /**
     * @param array ...$params
     */
    public function listen(...$params): void
    {
        /** @var false|object $event */
        $event = current($params);

        if (!is_object($event)) {
            return;
        }

        if ($event instanceof BeforeMiddleware) {
            $middleware = $event->getMiddleware();
            $this->handleStart($middleware);

            return;
        }
        if ($event instanceof AfterMiddleware) {
            $middleware = $event->getMiddleware();
            $this->handleDone($middleware);

            return;
        }
        if ($event instanceof ApplicationShutdown) {
            $this->traceMiddleware->terminate();

            return;
        }
    }

    private function handleStart(MiddlewareInterface $middleware): void
    {
        $currentTime = microtime(true);
        $currentClass = $middleware::class;

        if (null === $this->prevTime || null === $this->prevClass) {
            $this->prevTime = $currentTime;
            $this->prevClass = $currentClass;

            return;
        }
        $this->log('middleware in', $this->prevClass, $this->prevTime, $currentTime);
        $this->prevTime = $currentTime;
        $this->prevClass = $currentClass;
    }

    private function log(
        string $op,
        string $currentClass,
        float $prevTime,
        float $currentTime
    ): void {
        $parentSpan = Integration::currentTracingSpan();
        // If there is no tracing span active there is no need to handle the event
        if ($parentSpan === null) {
            return;
        }
        $spanContext = new SpanContext();
        $spanContext->setOp($op);
        $spanContext->setDescription($currentClass);
        $spanContext->setStartTimestamp($prevTime);
        $spanContext->setEndTimestamp($currentTime);
        $parentSpan->startChild($spanContext);
    }

    private function handleDone(MiddlewareInterface $middleware): void
    {
        $currentTime = microtime(true);
        $currentClass = $middleware::class;
        if (null === $this->prevTime) {
            return;
        }

        if ($this->isFirstPopFromMiddlewareStack) {
            $this->isFirstPopFromMiddlewareStack = false;
            $this->log('action', $currentClass, $this->prevTime, $currentTime);
            $this->prevTime = $currentTime;

            return;
        }

        $this->log('middleware out', $currentClass, $this->prevTime, $currentTime);
        $this->prevTime = $currentTime;
    }
}
