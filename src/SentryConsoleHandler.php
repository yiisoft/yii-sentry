<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Sentry\State\HubInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

/**
 * Catches console application exceptions and forwards them to Sentry.
 */
final class SentryConsoleHandler
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function handle(ConsoleErrorEvent $event): void
    {
        $this->hub->captureException($event->getError());
    }
}
