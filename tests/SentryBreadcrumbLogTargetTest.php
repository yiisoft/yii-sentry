<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests;

use Sentry\Event;
use Yiisoft\Yii\Sentry\Tests\Stub\Transport;

class SentryBreadcrumbLogTargetTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $self = new self();
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";
        $self->createSentryHub($eventKey);
        $self->getLogger()->flush();//drop init integration debug messages breadcrumb
    }

    public function testSendBreadCrumbWithError()
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";
        $this->createSentryHub($eventKey);
        $logger = $this->getLogger();
        $logger->debug('debug');
        $logger->error('error');
        $logger->flush(true);
        /** @var Event $event */
        $event = Transport::$events[$eventKey][0];
        $this->assertCount(1, Transport::$events[$eventKey]);
        $this->assertCount(2, $event->getBreadcrumbs());
    }

    public function testNotSendBreadCrumbWithoutError()
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";
        $this->createSentryHub($eventKey);
        $logger = $this->getLogger();
        $logger->debug('debug');
        $logger->info('info');
        $logger->flush(true);
        $this->assertCount(0, Transport::$events[$eventKey]);
    }

    public function testSendBreadCrumbUpLogLevel()
    {
        $methodName = debug_backtrace()[0]['function'];
        $eventKey = self::class . "::$methodName()";
        $this->createSentryHub($eventKey, ['log_level' => 'info',]);
        $logger = $this->getLogger();
        $logger->info('info');
        $logger->debug('debug');
        $logger->flush(true);
        $event = Transport::$events[$eventKey][0];
        $this->assertCount(1, Transport::$events[$eventKey]);
        $this->assertCount(2, $event->getBreadcrumbs());
    }
}
