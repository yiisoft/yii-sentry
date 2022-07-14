<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
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

The package needs PSR-compatible HTTP client so require it additionally to this package:

```
composer install php-http/guzzle7-adapter
composer install yiisoft/yii-sentry
```

Configure HTTP client (usually that is `config/common/sentry.php`):

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzleClientAdapter;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Yiisoft\Definitions\Reference;

return [
    HttpClient::class => GuzzleClient::class,
    HttpAsyncClient::class => [
        'class' => GuzzleClientAdapter::class,
        '__construct()' => [
            Reference::to(GuzzleClient::class),
        ],
    ],
];
```

Then add `SentryMiddleware` to main application middleware set and configure DSN in `config/params.php`: 

```php
return [
    // ...
    'middlewares' => [
        ErrorCatcher::class,
        SentryMiddleware::class, // <-- here
        SessionMiddleware::class,
        CookieMiddleware::class,
        CookieLoginMiddleware::class,
        LocaleMiddleware::class,
        Router::class,
    ],
    // ...
    'yiisoft/yii-sentry' => [
        'enabled' => true,
        'options' => [
            // <-- here. Set to `null` to disable error sending (note that it only prevents sending them via HTTP). To
            // disable interactions with Sentry SDK completely, remove middleware and the rest of the config.
            'dsn' => '...',
            'environment' => getenv('YII_ENV'),
        ],
    ],
    // ...
]
```

Console errors are captured by default, there is no need to configure anything.

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
