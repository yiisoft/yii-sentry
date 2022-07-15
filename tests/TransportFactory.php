<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use Sentry\Options;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

final class TransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private string $eventKey
    )
    {
    }

    public function create(Options $options): TransportInterface
    {
        return new Transport($this->eventKey);
    }
}
