<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Psr\Log\LoggerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Yiisoft\Yii\Console\Event\ApplicationShutdown;

final class SentryConsoleTransactionAdapter
{
    public function __construct(private LoggerInterface $logger, private SentryTraceConsoleListener $consoleListener)
    {
    }

    public function begin(?string $sentryTraceString = null): self
    {
        $hub = SentrySdk::getCurrentHub();
        if ($sentryTraceString) {
            $context = TransactionContext::fromSentryTrace($sentryTraceString);
        } else {
            $context = new TransactionContext();
        }
        $context->setOp('console sub task');

        $context->setStartTimestamp(microtime(true));

        $transaction = $hub->startTransaction($context);
        $transaction->setName('undefined command');
        $hub->setSpan($transaction);
        $this->consoleListener->setTransaction($transaction);

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

        $this->consoleListener->listenCommandTerminate(null);
        $this->consoleListener->listenShutdown(new ApplicationShutdown(0));
        $this->consoleListener->setTransaction(null);

        return $sentryTraceString;
    }
}
