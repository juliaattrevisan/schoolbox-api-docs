<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Mapper;

/**
 * Unit tests for the AI session {@see Mapper}.
 */
class MapperTest extends TestCase
{
    /**
     * Session log rows are summarised using the first viewable log and any error condition.
     */
    public function testSummariseSessionLogRowsUsesFirstViewableLogAndAnyError(): void
    {
        $mapper = new Mapper();

        $summary = $mapper->summariseSessionLogRows([
            [
                'id' => 10,
                'created_at' => '2026-01-01 10:00:00',
                'log_format' => null,
                'return_code' => null,
                'error_message' => null,
            ],
            [
                'id' => 11,
                'created_at' => '2026-01-01 10:01:00',
                'log_format' => 'agent-chat',
                'return_code' => 200,
                'error_message' => null,
            ],
            [
                'id' => 12,
                'created_at' => '2026-01-01 10:02:00',
                'log_format' => 'structured-output',
                'return_code' => 500,
                'error_message' => null,
            ],
        ]);

        $this->assertSame(
            [
                'derived_log_format' => 'agent-chat',
                'derived_has_error' => true,
            ],
            $summary
        );
    }

    /**
     * Session log rows without a viewable log or error return the default summary values.
     */
    public function testSummariseSessionLogRowsReturnsDefaultsForEmptySummary(): void
    {
        $mapper = new Mapper();

        $summary = $mapper->summariseSessionLogRows([
            [
                'id' => 10,
                'created_at' => '2026-01-01 10:00:00',
                'log_format' => null,
                'return_code' => null,
                'error_message' => null,
            ],
        ]);

        $this->assertSame(
            [
                'derived_log_format' => null,
                'derived_has_error' => false,
            ],
            $summary
        );
    }
}
