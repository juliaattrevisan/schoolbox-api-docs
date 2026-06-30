<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use Alaress\Database\Connector\NullConnector;
use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\OpenAi\Session\SessionSummariser;

/**
 * Unit tests for the AI session {@see SessionSummariser}.
 */
class SessionSummariserTest extends TestCase
{
    /**
     * Session log rows are summarised using the first viewable log and any error condition.
     */
    public function testHydrateSessionWithSummaryUsesFirstViewableLogAndAnyError(): void
    {
        $summariser = new SessionSummariser(new NullConnector());
        $session = new Model();

        $summariser->hydrateSessionWithSummaryInfo($session, [
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

        $this->assertSame('agent-chat', $session->getDerivedLogFormat());
        $this->assertTrue($session->getDerivedHasError());
    }

    /**
     * Session log rows without a viewable log or error return the default summary values.
     */
    public function testHydrateSessionWithSummaryUsesDefaultsForEmptySummary(): void
    {
        $summariser = new SessionSummariser(new NullConnector());
        $session = new Model();

        $summariser->hydrateSessionWithSummaryInfo($session, [
            [
                'id' => 10,
                'ai_session_id' => 'session-1',
                'created_at' => '2026-01-01 10:00:00',
                'log_format' => null,
                'return_code' => null,
                'error_message' => null,
            ],
        ]);

        $this->assertNull($session->getDerivedLogFormat());
        $this->assertFalse($session->getDerivedHasError());
    }

    /**
     * Session log rows are summarised in their supplied order.
     */
    public function testHydrateSessionWithSummaryUsesSuppliedOrder(): void
    {
        $summariser = new SessionSummariser(new NullConnector());
        $session = new Model();

        $summariser->hydrateSessionWithSummaryInfo($session, [
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

        $this->assertSame('supplied-first', $session->getDerivedLogFormat());
        $this->assertFalse($session->getDerivedHasError());
    }
}
