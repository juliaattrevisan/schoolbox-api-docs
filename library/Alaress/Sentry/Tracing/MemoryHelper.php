<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing;

use Sentry\Tracing\Span;

/**
 * Helper for tracking memory usage in Sentry spans and transactions.
 */
class MemoryHelper
{
    /**
     * Build memory usage for the start of a span.
     *
     * @return array
     */
    public static function buildStartSpanData(): array
    {
        return [
            'memory.usage_at_start' => memory_get_usage(true),
        ];
    }

    /**
     * Build memory usage for the end of a span.
     *
     * @return array
     */
    public static function buildEndSpanData(): array
    {
        return [
            'memory.usage_at_end' => memory_get_usage(true),
            'memory.peak_usage'   => memory_get_peak_usage(true),
        ];
    }

    /**
     * Add a memory delta to the given span.
     *
     * Expects that the span has had both start and end data added, from
     * `buildStartSpanData` and `buildEndSpanData` respectively.
     *
     * @param Span $span
     * @return void
     */
    public static function addMemoryDeltaData(Span $span): void
    {
        $memoryAtStart = $span->getData()['memory.usage_at_start'] ?? null;
        $memoryAtEnd   = $span->getData()['memory.usage_at_end'] ?? null;
        if (isset($memoryAtStart, $memoryAtEnd)) {
            $span->setData([
                'memory.delta' => $memoryAtEnd - $memoryAtStart,
            ]);
        }
    }
}
