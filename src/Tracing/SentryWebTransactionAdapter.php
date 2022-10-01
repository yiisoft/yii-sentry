<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Psr\Log\LoggerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Yiisoft\Log\Logger;

class SentryWebTransactionAdapter
{
    protected ?Logger $logger = null;

    public function __construct(LoggerInterface $logger, private SentryTraceMiddleware $middleware)
    {
        if ($logger instanceof Logger) {
            $this->logger = $logger;
        }
    }

    public function begin(?string $sentryTraceString = null): self
    {
        $hub = SentrySdk::getCurrentHub();
        if ($sentryTraceString) {
            $context = TransactionContext::fromSentryTrace($sentryTraceString);
        } else {
            $context = new TransactionContext();
        }
        $context->setOp('web sub task');

        $context->setStartTimestamp(microtime(true));

        $transaction = $hub->startTransaction($context);
        $transaction->setName('undefined action');
        $hub->setSpan($transaction);
        $this->middleware->setTransaction($transaction);

        $appContextStart = new SpanContext();
        $appContextStart->setOp('handle');
        $appContextStart->setStartTimestamp(microtime(true));
        $appSpan = $transaction->startChild($appContextStart);
        SentrySdk::getCurrentHub()->setSpan($appSpan);
        $this->middleware->setAppSpan($appSpan);

        return $this;
    }

    public function setName(string $name): self
    {
        SentrySdk::getCurrentHub()->getTransaction()?->setName($name);

        return $this;
    }

    public function getName(): ?string
    {
        return SentrySdk::getCurrentHub()->getTransaction()?->getName();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return $this
     */
    public function setData(array $data): self
    {
        SentrySdk::getCurrentHub()->getTransaction()?->setData($data);

        return $this;
    }

    public function commit(): ?string
    {
        $this->logger?->info('sentry force commit');
        $sentryTraceString = SentrySdk::getCurrentHub()->getSpan()?->toTraceparent();
        if (SentrySdk::getCurrentHub()->getTransaction() !== null) {
            $this->logger?->flush(true);
        }

        $this->middleware->terminate();

        return $sentryTraceString;
    }
}
