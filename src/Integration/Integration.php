<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Integration;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Integration\IntegrationInterface;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Yiisoft\Router\CurrentRoute;

use function Sentry\addBreadcrumb;
use function Sentry\configureScope;

final class Integration implements IntegrationInterface
{
    private static ?string $transaction = null;

    /**
     * Adds a breadcrumb if the integration is enabled for Yii3.
     */
    public static function addBreadcrumb(Breadcrumb $breadcrumb): void
    {
        $self = SentrySdk::getCurrentHub()->getIntegration(self::class);

        if (!$self instanceof self) {
            return;
        }

        addBreadcrumb($breadcrumb);
    }

    /**
     * Configures the scope if the integration is enabled for Yii3.
     */
    public static function configureScope(callable $callback): void
    {
        $self = SentrySdk::getCurrentHub()->getIntegration(self::class);

        if (!$self instanceof self) {
            return;
        }

        configureScope($callback);
    }

    /**
     * Block until all async events are processed for the HTTP transport.
     *
     * @internal This is not part of the public API and is here temporarily until
     *  the underlying issue can be resolved, this method will be removed.
     */
    public static function flushEvents(): void
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client !== null) {
            $client->flush();
        }
    }

    /**
     * Extract the readable name for a route.
     */
    public static function extractNameForRoute(?CurrentRoute $route): ?string
    {
        if (null === $route) {
            return null;
        }
        $routeName = null;

        if ($route->getName()) {
            $routeName = $route->getName();
        }

        if (empty($routeName) && $route->getUri()) {
            $routeName = $route->getUri()->getPath();
        }

        return $routeName;
    }

    /**
     * Retrieve the meta tags with tracing information to link this request to front-end requests.
     */
    public static function sentryTracingMeta(): string
    {
        $span = self::currentTracingSpan();

        if ($span === null) {
            return '';
        }

        return sprintf('<meta name="sentry-trace" content="%s"/>', $span->toTraceparent());
    }

    /**
     * Get the current active tracing span from the scope.
     *
     * @internal This is used internally as an easy way to retrieve the current active tracing span.
     */
    public static function currentTracingSpan(): ?Span
    {
        return SentrySdk::getCurrentHub()->getSpan();
    }

    public static function logLevelToBreadcrumbLevel(string $level): string
    {
        switch (strtolower($level)) {
            case 'debug':
                return Breadcrumb::LEVEL_DEBUG;
            case 'warning':
                return Breadcrumb::LEVEL_WARNING;
            case 'error':
                return Breadcrumb::LEVEL_ERROR;
            case 'critical':
            case 'alert':
            case 'emergency':
                return Breadcrumb::LEVEL_FATAL;
            case 'info':
            case 'notice':
            default:
                return Breadcrumb::LEVEL_INFO;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            $self = SentrySdk::getCurrentHub()->getIntegration(self::class);

            if (!$self instanceof self) {
                return $event;
            }

            if (empty($event->getTransaction())) {
                $event->setTransaction($self->getTransaction());
            }

            return $event;
        });
    }

    public static function getTransaction(): ?string
    {
        return self::$transaction;
    }

    public static function setTransaction(?string $transaction): void
    {
        self::$transaction = $transaction;
    }
}
