<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use Alaress\Database\Connector\NullConnector;
use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
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

        $summariser->hydrateSessionWithSummaryInfo(
            $session,
            [
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
            ],
            []
        );

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

        $summariser->hydrateSessionWithSummaryInfo(
            $session,
            [
                [
                    'id' => 10,
                    'ai_session_id' => 'session-1',
                    'created_at' => '2026-01-01 10:00:00',
                    'log_format' => null,
                    'return_code' => null,
                    'error_message' => null,
                ],
            ],
            []
        );

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

        $summariser->hydrateSessionWithSummaryInfo(
            $session,
            [
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
            ],
            []
        );

        $this->assertSame('supplied-first', $session->getDerivedLogFormat());
        $this->assertFalse($session->getDerivedHasError());
    }

    /**
     * Moderation event rows are summarised using the highest-priority status.
     */
    public function testHydrateSessionWithSummaryUsesMostImportantModerationStatus(): void
    {
        $summariser = new SessionSummariser(new NullConnector());
        $session = new Model();

        $summariser->hydrateSessionWithSummaryInfo(
            $session,
            [],
            [
                [
                    'id' => 10,
                    'target_ai_session_id' => 'session-1',
                    'status' => ModerationEventStatus::NO_BEHAVIOUR_FLAGGED->value,
                ],
                [
                    'id' => 11,
                    'target_ai_session_id' => 'session-1',
                    'status' => ModerationEventStatus::FURTHER_ACTION_TAKEN->value,
                ],
                [
                    'id' => 12,
                    'target_ai_session_id' => 'session-1',
                    'status' => ModerationEventStatus::REQUIRES_ATTENTION->value,
                ],
            ]
        );

        $this->assertSame(ModerationEventStatus::REQUIRES_ATTENTION->value, $session->getDerivedModerationStatus());
    }

    /**
     * Sessions without moderation event rows use a null moderation status.
     */
    public function testHydrateSessionWithSummaryUsesNullModerationStatusWhenNoEventsExist(): void
    {
        $summariser = new SessionSummariser(new NullConnector());
        $session = (new Model())->setDerivedModerationStatus(ModerationEventStatus::REQUIRES_ATTENTION);

        $summariser->hydrateSessionWithSummaryInfo($session, [], []);

        $this->assertNull($session->getDerivedModerationStatus());
    }

    /**
     * Multiple sessions are hydrated from rows grouped by session ID.
     */
    public function testHydrateSessionsWithSummaryInfoDelegatesToSingleSessionHydration(): void
    {
        $summariser = new class (new NullConnector()) extends SessionSummariser {
            public function getSessionLogRowsBySessionId(array $sessionIds): array
            {
                return [
                    'session-1' => [
                        [
                            'id' => 10,
                            'ai_session_id' => 'session-1',
                            'created_at' => '2026-01-01 10:00:00',
                            'log_format' => 'agent-chat',
                            'return_code' => 200,
                            'error_message' => null,
                        ],
                    ],
                    'session-2' => [
                        [
                            'id' => 11,
                            'ai_session_id' => 'session-2',
                            'created_at' => '2026-01-01 10:01:00',
                            'log_format' => 'guardrail',
                            'return_code' => 500,
                            'error_message' => null,
                        ],
                    ],
                ];
            }

            public function getModerationEventRowsBySessionId(array $sessionIds): array
            {
                return [
                    'session-1' => [
                        [
                            'id' => 20,
                            'target_ai_session_id' => 'session-1',
                            'status' => ModerationEventStatus::NO_ACTION_REQUIRED->value,
                        ],
                    ],
                    'session-2' => [
                        [
                            'id' => 21,
                            'target_ai_session_id' => 'session-2',
                            'status' => ModerationEventStatus::FURTHER_ACTION_REQUIRED->value,
                        ],
                    ],
                ];
            }
        };
        $firstSession = (new Model())->setId('session-1');
        $secondSession = (new Model())->setId('session-2');

        $summariser->hydrateSessionsWithSummaryInfo([$firstSession, $secondSession]);

        $this->assertSame('agent-chat', $firstSession->getDerivedLogFormat());
        $this->assertFalse($firstSession->getDerivedHasError());
        $this->assertSame(ModerationEventStatus::NO_ACTION_REQUIRED->value, $firstSession->getDerivedModerationStatus());

        $this->assertSame('guardrail', $secondSession->getDerivedLogFormat());
        $this->assertTrue($secondSession->getDerivedHasError());
        $this->assertSame(
            ModerationEventStatus::FURTHER_ACTION_REQUIRED->value,
            $secondSession->getDerivedModerationStatus()
        );
    }
}
