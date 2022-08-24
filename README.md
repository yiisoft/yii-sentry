<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <a href="https://sentry.io/" target="_blank">
      <img src="https://sentry-brand.storage.googleapis.com/sentry-wordmark-dark-280x84.png" alt="Sentry" width="280" height="84">
    </a>
    <h1 align="center">Yii Sentry</h1>
    <br>
</p>

The package provides [Sentry](https://sentry.io/) integration for Yii Framework

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii-sentry/v/stable.png)](https://packagist.org/packages/yiisoft/yii-sentry)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii-sentry/downloads.png)](https://packagist.org/packages/yiisoft/yii-sentry)
[![Build status](https://github.com/yiisoft/yii-sentry/workflows/build/badge.svg)](https://github.com/yiisoft/yii-sentry/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fyii-sentry%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/yii-sentry/master)
[![static analysis](https://github.com/yiisoft/yii-sentry/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/yii-sentry/actions?query=workflow%3A%22static+analysis%22)

## Installation

The package needs PSR-compatible HTTP client and factories so require it additionally to this package:

```bash
composer install httpsoft/http-message
composer install php-http/guzzle7-adapter
composer install yiisoft/yii-sentry
```

The first two can be replaced to other packages of your choice.

For handling console errors `yii-console` and `yii-event` packages are required additionally:

```bash
composer install yiisoft/yii-console
composer install yiisoft/yii-event
```

Configure HTTP factories and client (usually that is `config/common/sentry.php`):

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzleClientAdapter;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use HttpSoft\Message\RequestFactory;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UriFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Definitions\Reference;

return [
    // HTTP Factories
    StreamFactoryInterface::class => StreamFactory::class,
    RequestFactoryInterface::class => RequestFactory::class,
    LoggerInterface::class => NullLogger::class,
    UriFactoryInterface::class => UriFactory::class,
    ResponseFactoryInterface::class => ResponseFactory::class,
    // HTTP Client
    HttpClient::class => GuzzleClient::class,
    HttpAsyncClient::class => [
        'class' => GuzzleClientAdapter::class,
        '__construct()' => [
            Reference::to(HttpClient::class),
        ],
    ],
];
```

If you want to trace Guzzle requests and add Sentry headers to external queries, add the following:

```php 
GuzzleHttp\Client::class => static function (ContainerInterface $container) {
    $stack = new HandlerStack();
    $stack->setHandler(new CurlHandler());
    $factory = $container->get(GuzzleMiddlewareFactory::class);
    $middleware = static function (callable $handler) use ($factory): callable {
        return $factory->factory($handler);
    };

    $stack->push($middleware);

    return new GuzzleHttp\Client([
        'handler' => $stack,
    ]);
},
```


**Configure:**

Add the following code block to your `params.php` and define DSN. Also you can set "environment" and "release". Good example is to use TAG from gitlab.ci for it.
```php 
'yiisoft/yii-sentry' => [
    'options' => [
        'dsn' => '',
        'environment' => 'local', //SENTRY_ENVIRONMENT, //YII_ENV,
        'release' => 'dev',  //SENTRY_RELEASE, //TAG
        // @see: https://docs.sentry.io/platforms/php/configuration/options/#send-default-pii
        'send_default_pii' => true,
        'traces_sample_rate' => 1.0,
    ],
    'handleConsoleErrors' => true,
    'log_level' => 'warning',
    'tracing' => [
        // Indicates if the tracing integrations supplied by Sentry should be loaded
        'default_integrations'   => true,
    ],
]
```

Add `APP_START_TIME` constant into `index.php` and `yii.php`:

```php
define('APP_START_TIME', microtime(true));
```

Add log targets for breadcrumbs and tracing to `app/config/common/logger.php` or another config file with logger settings:

```php 
return [
    LoggerInterface::class => static function (
        /** your_another_log_target $your_log_target */
        \Yiisoft\Yii\Sentry\SentryBreadcrumbLogTarget $sentryLogTarget,
        Yiisoft\Yii\Sentry\Tracing\SentryTraceLogTarget $sentryTraceLogTarget
    ) {
        return new Logger([
        /** $your_log_target */
            $sentryLogTarget,
            $sentryTraceLogTarget
        ]);
    }
];
```
> Note: **If you want to see your logs in sentry timeline**, you need to use keys (float)'**time**' and (float)'**elapsed**' in log context array.

Add DB log decorator for tracing db queries in `app/config/params.php`:

```php
'yiisoft/yii-cycle' => [
    // DBAL config
    'dbal' => [
        // SQL query logger. Definition of Psr\Log\LoggerInterface
        // For example, \Yiisoft\Yii\Cycle\Logger\StdoutQueryLogger::class
        'query-logger' => \Yiisoft\Yii\Sentry\DbLoggerDecorator::class,
        /**
         * ...
         * your another db settings 
         **/
    ]
]
```

add into app/config/params.php into middleware section  SetRequestIpMiddleware
```php
    'middlewares' => [
        ErrorCatcher::class,
        \Yiisoft\Yii\Sentry\Http\SetRequestIpMiddleware::class, //add this
        Router::class,
    ],
```

Add `SentryTraceMiddleware` to `app/config/common/router.php`:
```php
  RouteCollectionInterface::class => static function (RouteCollectorInterface $collector) use ($config) {
        $collector
            ->middleware(FormatDataResponse::class)
            ->middleware(JsonParseMiddleware::class)
            ->middleware(ExceptionMiddleware::class)
            ->middleware(\Yiisoft\Yii\Sentry\Tracing\SentryTraceMiddleware::class) // add this
            ->addGroup(
                Group::create('')
                    ->routes(...$config->get('routes'))
            );

        return new RouteCollection($collector);
    },
 ```



If your transaction is too heavy, you can slice it to several transactions with clearing log buffer. Use `SentryConsoleTransactionAdapter` or `SentryWebTransactionAdapter`. For example:

```php
/** some code with default transaction */
/** commit default transaction and send data to sentry server */
$sentryTraceString = $this->sentryTransactionAdapter->commit();
while ($currentDate <= $endDate) {
    $this->sentryTransactionAdapter->begin($sentryTraceString)
        ->setName('my_heavy_operation/iteration')
        ->setData(['date' => $currentDate->format('Y-m-d')]);

    $this->process($currentDate, $sentryTraceString);
    $this->sentryTransactionAdapter->commit();
}

$this->sentryTransactionAdapter->begin($sentryTraceString)
    ->setName('my_heavy_operation done, terminating application');
/** transaction will commit when application is terminated */
```
In this example all new transactions will linked to transaction with `$sentryTraceString`.


In `options` you can also pass additional Sentry configuration. See 
[official Sentry docs](https://docs.sentry.io/platforms/php/configuration/options/) for keys and values.

## Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

## Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```shell
./vendor/bin/infection
```

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev). To run static analysis:

```shell
./vendor/bin/psalm
```
