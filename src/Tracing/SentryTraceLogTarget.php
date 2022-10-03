<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Sentry\Tracing;

use Sentry\Tracing\SpanContext;
use Yiisoft\Log\Message;
use Yiisoft\Log\Target;
use Yiisoft\Yii\Sentry\Integration\Integration;

final class SentryTraceLogTarget extends Target
{
    protected function export(): void
    {
        array_map(function (Message $message): Message {
            /** @var array<string, mixed> $context */
            $context = $message->context();
            $category = (string)($context['category'] ?? 'log');
            $time = (float)($message->context()['time'] ?? microtime(true));
            unset($context['category'], $context['time']);
            if (
                array_key_exists('trace', $context)
                && empty($context['trace'])
            ) {
                unset($context['trace']);
            }
            if (!empty($context['memory']) && is_numeric($context['memory'])) {
                $context['memory'] = round(
                    ((float)$context['memory'] / (1024 * 1024)),
                    2
                ) . 'MB';
            }
            $parentSpan = Integration::currentTracingSpan();

            // If there is no tracing span active there is no need to handle the event
            if ($parentSpan === null) {
                return $message;
            }
            $spanContext = new SpanContext();
            if (isset($context['tags']) && is_array($context['tags'])) {
                /**  @psalm-suppress MixedArgumentTypeCoercion */
                $spanContext->setTags($context['tags']);
                unset($context['tags']);
            }
            $spanContext->setOp($category);
            $spanContext->setDescription($message->message());
            $spanContext->setStartTimestamp($time);
            $elapsed = empty($context['elapsed']) ? null : (float)$context['elapsed'];
            if ($elapsed) {
                $spanContext->setEndTimestamp($time + $elapsed);
            }
            $spanContext->setData($context);

            $parentSpan->startChild($spanContext);

            return $message;
        }, $this->getMessages());
    }
}
