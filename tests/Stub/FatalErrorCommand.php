<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FatalErrorCommand extends BaseCommand
{
    public function execute(InputInterface $input, OutputInterface $output)
    {
        trigger_error('Console fatal error test.', E_USER_ERROR);
    }
}
