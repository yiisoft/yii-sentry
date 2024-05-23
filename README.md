<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <a href="https://sentry.io/" target="_blank">
      <img src="https://sentry-brand.storage.googleapis.com/sentry-wordmark-dark-280x84.png" alt="Sentry" width="280" height="84">
    </a>
    <h1 align="center">Yii Sentry</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii-sentry/v/stable.png)](https://packagist.org/packages/yiisoft/yii-sentry)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii-sentry/downloads.png)](https://packagist.org/packages/yiisoft/yii-sentry)
[![Build status](https://github.com/yiisoft/yii-sentry/workflows/build/badge.svg)](https://github.com/yiisoft/yii-sentry/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-sentry/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fyii-sentry%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/yii-sentry/master)
[![static analysis](https://github.com/yiisoft/yii-sentry/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/yii-sentry/actions?query=workflow%3A%22static+analysis%22)

The package provides [Sentry](https://sentry.io/) integration for [Yii Framework](https://www.yiiframework.com/).

## Requirements

- PHP 8.0 or higher.

## Installation

The package could be installed with [Composer](https://getcomposer.org).

The package needs PSR-compatible HTTP client and factories so require it additionally to this package:

```shell
composer install httpsoft/http-message
composer install php-http/guzzle7-adapter
composer install yiisoft/yii-sentry
```

The first two can be replaced to other packages of your choice.

For handling console errors `yii-console` and `yii-event` packages are required additionally:

```shell
composer install yiisoft/yii-console
composer install yiisoft/yii-event
```

Configure HTTP factories and client (usually that is `config/common/di/sentry.php`):

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Http\Adapter\Guzzle7\Client as GuzzleClientAdapter;
use Http\Client\HttpAsyncClient;
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
            Reference::to(ClientInterface::class),
        ],
    ],
];
```

Then add `SentryMiddleware` to main application middleware set and configure DSN in `config/params.php`. Console errors
are captured by default, there is no need to configure anything.

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
        'handleConsoleErrors' => false, // Add to disable console errors.
        'options' => [
            // Set to `null` to disable error sending (note that in case of web application errors it only prevents
            // sending them via HTTP). To disable interactions with Sentry SDK completely, remove middleware and the
            // rest of the config.
            'dsn' => $_ENV['SENTRY_DSN'] ?? null,
            'environment' => $_ENV['YII_ENV'] ?? null, // Add to separate "production" / "staging" environment errors.
        ],
    ],
    // ...
]
```

Note that fatal errors are handled too.

In `options` you can also pass additional Sentry configuration. See
[official Sentry docs](https://docs.sentry.io/platforms/php/configuration/options/) for keys and values.

## Documentation

- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place for that.
You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## License

The Yii Sentry is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
