<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tests\Stub;

class ContextException extends \Exception
{
    public array $context = [];

    public function addContext(mixed $contextItem): self
    {
        $this->context[] = $contextItem;

        return $this;
    }

    public function context(): array
    {
        return $this->context;
    }
}
