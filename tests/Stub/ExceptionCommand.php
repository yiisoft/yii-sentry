<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use RuntimeException;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExceptionCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new RuntimeException('Console exception test.');
    }
}
