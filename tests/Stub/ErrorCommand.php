<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use RuntimeException;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ErrorCommand extends BaseCommand
{
    public function execute(InputInterface $input, OutputInterface $output)
    {
        throw new RuntimeException('Sentry console test.');
    }
}
