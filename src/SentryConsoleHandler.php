<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Sentry\State\HubInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

/**
 * Catches console application error and forwards them to Sentry.
 */
final class SentryConsoleHandler
{
    private HubInterface $hub;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    public function handle(ConsoleErrorEvent $event): void
    {
        $this->hub->captureException($event->getError());
    }
}
