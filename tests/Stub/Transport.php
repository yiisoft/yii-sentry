<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

final class Transport implements TransportInterface
{
    public static array $events = [];

    public function __construct(
        private string $eventKey
    ) {
        self::$events[$this->eventKey] = [];
    }

    public function send(Event $event): Result
    {
        self::$events[$this->eventKey][] = $event;

        return new Result(ResultStatus::skipped(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
