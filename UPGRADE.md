Upgrading Instructions for Yii Sentry
=====================================

Upgrade from 2.0
-----------------------
* Consult Sentry 4.x upgrade guide https://github.com/getsentry/sentry-php/blob/4.0.0/UPGRADE-4.0.md. Some notable changes are:
  * Configuration options (`['yiisoft/yii-sentry']['options']`) changes: `send_attempts`, `ignore_errors`, `logger` and `enable_compression`.
  * In case of custom DI configuration of HTTP client, `TransportFactoryInterface` or logger you may need to update your DI configuration like this:
```php di/sentry.php
Options::class => [
    'class' => Options::class,
    '__construct()' => [
        $params['yiisoft/yii-sentry']['options'],
    ],
    'setTransport()' => Reference::to(CustomTransportInterfaceImplementation::class),
    'setHttpClient()' => Reference::to(CustomHttpClient::class), 
    'setLogger()' => Reference::to(CustomLoggerInterfaceImplementation::class), 
],
```