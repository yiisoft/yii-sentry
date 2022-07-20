<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\State\HubInterface;
use Throwable;

/**
 * Catches web application exceptions and forwards them to Sentry.
 * In order to catch all exceptions, add it right after `ErrorCatcher` in the main middleware set.
 */
final class SentryMiddleware implements MiddlewareInterface
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->hub->captureException($e);
            throw $e;
        }
    }
}
