<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Yiisoft\Yii\Console\Event\ApplicationShutdown;

final class SentryTraceConsoleListener
{
    /**
     * The current active transaction.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected ?Transaction $transaction = null;
    /**
     * The span for the `app.handle` part of the application.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected ?Span $appSpan = null;
    /**
     * The span for the `app.handle` part of the application.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    protected ?Span $bootSpan = null;
    /**
     * The timestamp of application bootstrap completion.
     */
    private ?float $bootedTimestamp;

    public function __construct(
        private HubInterface $hub,
    ) {
        $this->bootedTimestamp = microtime(true);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function listenAppStart(): void
    {
        $this->startTransaction();
    }

    private function startTransaction(): void
    {
        $requestStartTime = defined('APP_START_TIME') ? (float)APP_START_TIME : microtime(true);
        $context = new TransactionContext();
        $context->setOp('console');
        $context->setStartTimestamp($requestStartTime);
        $this->transaction = $this->hub->startTransaction($context);
        $this->transaction->setName('undefined command');
        SentrySdk::getCurrentHub()->setSpan($this->transaction);
        $this->addAppBootstrapSpan();
    }

    private function addAppBootstrapSpan(): void
    {
        if ($this->bootedTimestamp === null || $this->transaction === null) {
            return;
        }
        $appStartTime = defined('APP_START_TIME') ? (float)APP_START_TIME : microtime(true);

        $spanContextStart = new SpanContext();
        $spanContextStart->setOp('app.bootstrap');
        $spanContextStart->setStartTimestamp($appStartTime);
        $spanContextStart->setEndTimestamp($this->bootedTimestamp);

        $span = $this->transaction->startChild($spanContextStart);

        // Consume the booted timestamp, because we don't want to report the bootstrap span more than once
        $this->bootedTimestamp = null;

        // Add more information about the bootstrap section if possible
        $this->addBootDetailTimeSpans($span);

        $this->bootSpan = $span;
    }

    private function addBootDetailTimeSpans(Span $bootstrap): void
    {
        if (!defined('SENTRY_AUTOLOAD') || !SENTRY_AUTOLOAD || !is_numeric(SENTRY_AUTOLOAD)) {
            return;
        }

        $autoload = new SpanContext();
        $autoload->setOp('autoload');
        $autoload->setStartTimestamp($bootstrap->getStartTimestamp());
        $autoload->setEndTimestamp((float)SENTRY_AUTOLOAD);

        $bootstrap->startChild($autoload);
    }

    public function listenBeginCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $input = $event->getInput();
        $this->startCommand($command, $input);
    }

    private function startCommand(?Command $command, InputInterface $input): void
    {
        if ($this->transaction === null) {
            return;
        }
        $name = $command?->getName() ?? 'undefined command';
        $inputArgs = [
            'arguments' => $input->getArguments(),
            'options' => $input->getOptions(),
        ];
        $this->transaction->setData(
            [
                'name' => $name,
                'input' => $inputArgs,
            ]
        );
        $this->transaction->setName($name);

        $bootstrapSpan = $this->bootSpan;

        $appContextStart = new SpanContext();
        $appContextStart->setOp('app.handle');
        $startTimestamp = $bootstrapSpan ? $bootstrapSpan->getEndTimestamp() : microtime(true);
        $appContextStart->setStartTimestamp($startTimestamp);

        $this->appSpan = $this->transaction->startChild($appContextStart);

        SentrySdk::getCurrentHub()->setSpan($this->appSpan);
    }

    public function listenShutdown(ApplicationShutdown $event): void
    {
        $exitCode = $event->getExitCode();
        $this->terminate($exitCode);
    }

    public function terminate(int $exitCode): void
    {
        if ($this->transaction !== null) {
            $this->appSpan?->finish();
            $this->appSpan = null;

            $this->transaction->setTags(['exitCode' => (string)$exitCode]);
            // Make sure we set the transaction and not have a child span in the Sentry SDK
            // If the transaction is not on the scope during finish, the `trace.context` is wrong
            SentrySdk::getCurrentHub()->setSpan($this->transaction);

            $this->transaction->finish();
            $this->transaction = null;
        }
    }

    public function listenCommandTerminate(?ConsoleTerminateEvent $terminateEvent): void
    {
        $this->appSpan?->finish(microtime(true));
    }
}
