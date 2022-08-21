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
 * This middleware enriches the Sentry scope with the IP address of the request.
 * We do this ourself instead of letting the PHP SDK handle this because we want
 * the IP from the Psr request because it takes into account trusted proxies.
 */
class SetRequestIpMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
