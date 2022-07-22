<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\ErrorHandler\Exception\ErrorException;

final class ErrorHandlerExceptionCommand extends BaseCommand
{
    public function execute(InputInterface $input, OutputInterface $output)
    {
        throw new ErrorException('Console error handler exception test.');
    }
}
