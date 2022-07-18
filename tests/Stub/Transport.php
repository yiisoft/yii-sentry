<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Sentry\Event;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\Transport\TransportInterface;

final class Transport implements TransportInterface
{
    public static array $events = [];

    public function __construct(
        private string $eventKey
    ) {
        self::$events[$this->eventKey] = [];
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event): PromiseInterface
    {
        self::$events[$this->eventKey][] = $event;

        return new FulfilledPromise(new Response(ResponseStatus::skipped(), $event));
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
