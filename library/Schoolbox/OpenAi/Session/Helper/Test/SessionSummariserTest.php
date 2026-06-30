<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Helper\Test;

use Alaress\Database\Connector\NullConnector;
use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Helper\SessionSummariser;

/**
 * Unit tests for the AI session {@see SessionSummariser}.
 */
class SessionSummariserTest extends TestCase
{
    /**
     * Session log rows are summarised using the first viewable log and any error condition.
     */
    public function testSummariseSessionLogRowsUsesFirstViewableLogAndAnyError(): void
    {
        $summariser = new SessionSummariser(new NullConnector());

        $summary = $summariser->summariseSessionLogRows([
            [
                'id' => 10,
                'ai_session_id' => 'session-1',
                'created_at' => '2026-01-01 10:00:00',
                'log_format' => null,
                'return_code' => null,
                'error_message' => null,
            ],
            [
                'id' => 11,
                'ai_session_id' => 'session-1',
                'created_at' => '2026-01-01 10:01:00',
                'log_format' => 'agent-chat',
                'return_code' => 200,
                'error_message' => null,
            ],
            [
                'id' => 12,
                'ai_session_id' => 'session-1',
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
        $summariser = new SessionSummariser(new NullConnector());

        $summary = $summariser->summariseSessionLogRows([
            [
                'id' => 10,
                'ai_session_id' => 'session-1',
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

    /**
     * Session log rows are summarised in their supplied order.
     */
    public function testSummariseSessionLogRowsUsesSuppliedOrder(): void
    {
        $summariser = new SessionSummariser(new NullConnector());

        $summary = $summariser->summariseSessionLogRows([
            [
                'id' => 12,
                'ai_session_id' => 'session-1',
                'created_at' => '2026-01-01 10:02:00',
                'log_format' => 'supplied-first',
                'return_code' => 200,
                'error_message' => null,
            ],
            [
                'id' => 11,
                'ai_session_id' => 'session-1',
                'created_at' => '2026-01-01 10:01:00',
                'log_format' => 'chronological-first',
                'return_code' => 200,
                'error_message' => null,
            ],
        ]);

        $this->assertSame(
            [
                'derived_log_format' => 'supplied-first',
                'derived_has_error' => false,
            ],
            $summary
        );
    }
}
