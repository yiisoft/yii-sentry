<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yiisoft\Yii\Sentry\HubBootstrapper;

return [
    static function (ContainerInterface $container): void {
        $bootstrapper = $container->get(HubBootstrapper::class);
        $bootstrapper->bootstrap();
    },
];
