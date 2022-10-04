<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

use JetBrains\PhpStorm\Pure;
use Throwable;

class ContextException extends \Exception
{
    public array $context = [];

    public function addContext($contextItem): self
    {
        $this->context[] = $contextItem;

        return $this;
    }

    public function context(): array
    {
        return $this->context;
    }
}
