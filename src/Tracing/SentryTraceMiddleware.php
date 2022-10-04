<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Yii\Sentry\Integration\Integration;

final class SentryTraceMiddleware implements MiddlewareInterface
{
    /**
     * The current active transaction.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected ?\Sentry\Tracing\Transaction $transaction = null;
    /**
     * The span for the `app.handle` part of the application.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected ?\Sentry\Tracing\Span $appSpan = null;
    /**
     * The timestamp of application bootstrap completion.
     */
    private ?float $bootedTimestamp;
    /**
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private ?ServerRequestInterface $request = null;
    /**
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private ?ResponseInterface $response = null;

    public function __construct(
        private HubInterface $hub,
        private ?CurrentRoute $currentRoute
    ) {
        $this->bootedTimestamp = microtime(true);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->startTransaction($request, $this->hub);
        $this->request = $request;
        $this->response = $handler->handle($request);

        return $this->response;
    }

    private function startTransaction(
        ServerRequestInterface $request,
        HubInterface $sentry
    ): void {
        $requestStartTime = $this->getStartTime($request) ?? microtime(true);

        if ($request->hasHeader('sentry-trace')) {
            $headers = $request->getHeader('sentry-trace');
            $header = reset($headers);
            $context = TransactionContext::fromSentryTrace($header);
        } else {
            $context = new TransactionContext();
        }

        $context->setOp('http.server');
        $context->setData([
            'url' => '/' . ltrim($request->getUri()->getPath(), '/'),
            'method' => strtoupper($request->getMethod()),
        ]);
        $context->setStartTimestamp($requestStartTime);

        $this->transaction = $sentry->startTransaction($context);

        // Setting the Transaction on the Hub
        SentrySdk::getCurrentHub()->setSpan($this->transaction);

        $bootstrapSpan = $this->addAppBootstrapSpan($request);

        $appContextStart = new SpanContext();
        $appContextStart->setOp('app.handle');
        $appContextStart->setStartTimestamp(
            $bootstrapSpan
                ? $bootstrapSpan->getEndTimestamp()
                : microtime(true)
        );

        $this->appSpan = $this->transaction->startChild($appContextStart);

        SentrySdk::getCurrentHub()->setSpan($this->appSpan);
    }

    private function addAppBootstrapSpan(ServerRequestInterface $request): ?Span
    {
        if ($this->bootedTimestamp === null) {
            return null;
        }
        if (null === $this->transaction) {
            return null;
        }

        $appStartTime = $this->getStartTime($request);

        if ($appStartTime === null) {
            return null;
        }

        $spanContextStart = new SpanContext();
        $spanContextStart->setOp('app.bootstrap');
        $spanContextStart->setStartTimestamp($appStartTime);
        $spanContextStart->setEndTimestamp($this->bootedTimestamp);

        $span = $this->transaction->startChild($spanContextStart);

        // Consume the booted timestamp, because we don't want to report the bootstrap span more than once
        $this->bootedTimestamp = null;

        // Add more information about the bootstrap section if possible
        $this->addBootDetailTimeSpans($span);

        return $span;
    }

    private function addBootDetailTimeSpans(Span $bootstrap): void
    {
        if (!defined('SENTRY_AUTOLOAD')
            || !SENTRY_AUTOLOAD
            || !is_numeric(SENTRY_AUTOLOAD)
        ) {
            return;
        }

        $autoload = new SpanContext();
        $autoload->setOp('autoload');
        $autoload->setStartTimestamp($bootstrap->getStartTimestamp());
        $autoload->setEndTimestamp((float)SENTRY_AUTOLOAD);

        $bootstrap->startChild($autoload);
    }

    public function terminate(): void
    {
        if ($this->transaction !== null) {
            $this->appSpan?->finish();

            // Make sure we set the transaction and not have a child span in the Sentry SDK
            // If the transaction is not on the scope during finish, the trace.context is wrong
            SentrySdk::getCurrentHub()->setSpan($this->transaction);

            if (null !== $this->request) {
                $this->hydrateRequestData($this->request);
            }

            if (null !== $this->response) {
                $this->hydrateResponseData($this->response);
            }

            $this->transaction->finish();
        }
    }

    private function hydrateRequestData(ServerRequestInterface $request): void
    {
        $route = $this->currentRoute;
        if (null === $this->transaction) {
            return;
        }

        if ($route) {
            $this->updateTransactionNameIfDefault(
                Integration::extractNameForRoute($route)
            );

            $this->transaction->setData([
                'name' => Integration::extractNameForRoute($route),
                'method' => $request->getMethod(),
            ]);
        }

        $this->updateTransactionNameIfDefault(
            '/' . ltrim($request->getUri()->getPath(), '/')
        );
    }

    private function updateTransactionNameIfDefault(?string $name): void
    {
        // Ignore empty names (and `null`) for caller convenience
        if (empty($name)) {
            return;
        }
        if (null === $this->transaction) {
            return;
        }
        // If the transaction already has a name other than the default
        // ignore the new name, this will most occur if the user has set a
        // transaction name themself before the application reaches this point
        if ($this->transaction->getName() !== TransactionContext::DEFAULT_NAME
        ) {
            return;
        }

        $this->transaction->setName($name);
    }

    private function hydrateResponseData(ResponseInterface $response): void
    {
        $this->transaction?->setHttpStatus($response->getStatusCode());
    }

    private function getStartTime(ServerRequestInterface $request): ?float
    {
        /** @psalm-suppress MixedAssignment */
        $attStartTime = $request->getAttribute('applicationStartTime');
        if (is_numeric($attStartTime) && !empty((float)$attStartTime)) {
            $requestStartTime = (float)$attStartTime;
        } else {
            $requestStartTime = !empty($request->getServerParams()['REQUEST_TIME_FLOAT'])
                ? (float)$request->getServerParams()['REQUEST_TIME_FLOAT']
                : (defined('APP_START_TIME')
                    ? (float)APP_START_TIME
                    : null);
        }

        return $requestStartTime;
    }
}
