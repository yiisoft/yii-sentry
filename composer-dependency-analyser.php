<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    ->ignoreErrorsOnPackages(['psr/container', 'psr/log'], [ErrorType::SHADOW_DEPENDENCY])
    // `Yiisoft\ErrorHandler\Exception\ErrorException::class` in config/params.php is only used as a class-name
    // string to compare against in the default `before_send` filter. `::class` on a fully-qualified name does not
    // require the class to be loaded, so the integration degrades gracefully when yiisoft/error-handler isn't
    // installed. It is an intentionally optional integration, not a hard runtime dependency.
    ->ignoreErrorsOnPackages(['yiisoft/error-handler'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
