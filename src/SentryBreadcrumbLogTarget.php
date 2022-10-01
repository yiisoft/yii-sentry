<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Yiisoft\Log\Target;

final class SentryBreadcrumbLogTarget extends Target
{
    public function __construct(private SentryLogAdapter $handler)
    {
        parent::__construct();
    }

    /**
     * @psalm-suppress MixedArgument
     */
    protected function export(): void
    {
        foreach ($this->getMessages() as $message) {
            $this->handler->breadcrumb(
                $message->level(),
                $message->message(),
                $message->context()
            );
        }

        foreach ($this->getMessages() as $message) {
            $this->handler->log(
                $message->level(),
                $message->message(),
                $message->context()
            );
        }
    }
}
