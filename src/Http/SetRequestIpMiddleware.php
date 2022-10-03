<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This middleware extends Sentry scope with IP address of the request. Because PSR request is used, it takes trusted 
 * proxies into account, which does not happen during automatic handling by PHP SDK.
 */
class SetRequestIpMiddleware implements MiddlewareInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($this->container->has(HubInterface::class)) {
            /** @var HubInterface $sentry */
            $sentry = $this->container->get(HubInterface::class);
            $client = $sentry->getClient();

            if ($client !== null && $client->getOptions()->shouldSendDefaultPii()) {
                $sentry->configureScope(static function (Scope $scope) use ($request): void {
                    $scope->setUser([
                        'ip_address' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
                    ]);
                });
            }
        }

        return $handler->handle($request);
    }
}
