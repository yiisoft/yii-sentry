<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

final class Command extends BaseCommand
{
    public function execute(InputInterface $input, OutputInterface $output)
    {
        return ExitCode::OK;
    }
}
