<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class DbLoggerDecorator implements LoggerInterface
{
    use LoggerTrait;

    public const LOG_CATEGORY = 'db_app';
    public const ORM_QUERY_LOG_CATEGORY = 'db_sys';

    public function __construct(
        private LoggerInterface $defaultLogger
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = array()): void
    {
        $extendedContext = $this->extendContext($context, (string)$message);

        $this->defaultLogger->log($level, $message, $extendedContext);
    }

    private function extendContext(array $context, string $message): array
    {
        if ($this->isPostgresSystemQuery($message)) {
            $context['category'] = self::ORM_QUERY_LOG_CATEGORY;
        } else {
            $context['category'] = self::LOG_CATEGORY;
        }

        $context['time'] = empty($context['time']) ? microtime(true) : (float)$context['time'];

        return $context;
    }

    protected function isPostgresSystemQuery(string $srcQuery): bool
    {
        $query = strtolower($srcQuery);

        return
            strpos($query, 'tc.constraint_name')
            || strpos($query, 'pg_indexes')
            || strpos($query, 'tc.constraint_name')
            || strpos($query, 'pg_constraint')
            || strpos($query, 'information_schema')
            || strpos($query, 'pg_class');
    }
}
