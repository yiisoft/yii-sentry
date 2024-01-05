<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Psr\Log\LoggerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;

class SentryWebTransactionAdapter
{
    public function __construct(private LoggerInterface $logger, private SentryTraceMiddleware $middleware)
    {
    }

    public function begin(?string $sentryTraceString = null, string $baggage = ''): self
    {
        $hub = SentrySdk::getCurrentHub();
        if ($sentryTraceString) {
            $context = TransactionContext::fromHeaders($sentryTraceString, $baggage);
        } else {
            $context = new TransactionContext();
        }
        $context->setOp('web subtask');

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
        $this->logger->info('sentry force commit');
        $sentryTraceString = SentrySdk::getCurrentHub()->getSpan()?->toTraceparent();
        if (method_exists($this->logger, 'flush') && SentrySdk::getCurrentHub()->getTransaction() !== null) {
            $this->logger->flush(true);
        }

        $this->middleware->terminate();

        return $sentryTraceString;
    }
}
