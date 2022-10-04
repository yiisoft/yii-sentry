<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Integration;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\Integration\IntegrationInterface;
use Sentry\SentrySdk;
use Sentry\State\Scope;

class ExceptionContextIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(
            static function (Event $event, ?EventHint $hint = null): Event {
                $self = SentrySdk::getCurrentHub()->getIntegration(self::class);

                if (!$self instanceof self
                    || $hint === null
                    || $hint->exception === null
                    || !method_exists($hint->exception, 'context')
                    || !is_array($hint->exception->context())
                ) {
                    return $event;
                }
                $event->setExtra(['exception_context' => $hint->exception->context()]);

                return $event;
            }
        );
    }
}
