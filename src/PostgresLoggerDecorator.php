<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class PostgresLoggerDecorator implements LoggerInterface
{
    use LoggerTrait;

    private const LOG_CATEGORY = 'db_app';
    private const ORM_QUERY_LOG_CATEGORY = 'db_sys';

    public function __construct(
        private LoggerInterface $defaultLogger
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
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
            str_contains($query, 'tc.constraint_name')
            || str_contains($query, 'pg_indexes')
            || str_contains($query, 'tc.constraint_name')
            || str_contains($query, 'pg_constraint')
            || str_contains($query, 'information_schema')
            || str_contains($query, 'pg_class');
    }
}
