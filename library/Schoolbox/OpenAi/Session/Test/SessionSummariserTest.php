<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\OpenAi\Session\SessionSummariser;
use Schoolbox\Test\MockConnector;

/**
 * Unit tests for the AI session {@see SessionSummariser}.
 */
#[AllowMockObjectsWithoutExpectations]
class SessionSummariserTest extends TestCase
{
    /**
     * Session log rows are summarised using the first viewable log and any error condition.
     */
    public function testHydrateSessionWithSummaryUsesFirstViewableLogAndAnyError(): void
    {
        $summariser = $this->createSummariserWithRows(
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
        $session = new Model()->setId('session-1');

        $summariser->hydrateSessionsWithSummaryInfo([$session]);

        $this->assertSame('agent-chat', $session->getDerivedLogFormat());
        $this->assertTrue($session->getDerivedHasError());
    }

    /**
     * Session log rows without a viewable log or error return the default summary values.
     */
    public function testHydrateSessionWithSummaryUsesDefaultsForEmptySummary(): void
    {
        $summariser = $this->createSummariserWithRows(
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
        $session = new Model()->setId('session-1');

        $summariser->hydrateSessionsWithSummaryInfo([$session]);

        $this->assertNull($session->getDerivedLogFormat());
        $this->assertFalse($session->getDerivedHasError());
    }

    /**
     * Session log rows are summarised in their supplied order.
     */
    public function testHydrateSessionWithSummaryUsesSuppliedOrder(): void
    {
        $summariser = $this->createSummariserWithRows(
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
        $session = new Model()->setId('session-1');

        $summariser->hydrateSessionsWithSummaryInfo([$session]);

        $this->assertSame('supplied-first', $session->getDerivedLogFormat());
        $this->assertFalse($session->getDerivedHasError());
    }

    /**
     * Moderation event rows are summarised using the highest-priority status.
     */
    public function testHydrateSessionWithSummaryUsesMostImportantModerationStatus(): void
    {
        $summariser = $this->createSummariserWithRows(
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
        $session = new Model()->setId('session-1');

        $summariser->hydrateSessionsWithSummaryInfo([$session]);

        $this->assertSame(ModerationEventStatus::REQUIRES_ATTENTION->value, $session->getDerivedModerationStatus());
    }

    /**
     * Sessions without moderation event rows use a null moderation status.
     */
    public function testHydrateSessionWithSummaryUsesNullModerationStatusWhenNoEventsExist(): void
    {
        $summariser = $this->createSummariserWithRows([], []);
        $session = new Model()->setDerivedModerationStatus(ModerationEventStatus::REQUIRES_ATTENTION);

        $summariser->hydrateSessionsWithSummaryInfo([$session]);

        $this->assertNull($session->getDerivedModerationStatus());
    }

    /**
     * Multiple sessions are hydrated from rows grouped by session ID.
     */
    public function testHydrateSessionsWithSummaryInfoDelegatesToSingleSessionHydration(): void
    {
        $summariser = $this->createSummariserWithRows(
            [
                [
                    'id' => 10,
                    'ai_session_id' => 'session-1',
                    'created_at' => '2026-01-01 10:00:00',
                    'log_format' => 'agent-chat',
                    'return_code' => 200,
                    'error_message' => null,
                ],
                [
                    'id' => 11,
                    'ai_session_id' => 'session-2',
                    'created_at' => '2026-01-01 10:01:00',
                    'log_format' => 'guardrail',
                    'return_code' => 500,
                    'error_message' => null,
                ],
            ],
            [
                [
                    'id' => 20,
                    'target_ai_session_id' => 'session-1',
                    'status' => ModerationEventStatus::NO_ACTION_REQUIRED->value,
                ],
                [
                    'id' => 21,
                    'target_ai_session_id' => 'session-2',
                    'status' => ModerationEventStatus::FURTHER_ACTION_REQUIRED->value,
                ],
            ]
        );
        $firstSession = new Model()->setId('session-1');
        $secondSession = new Model()->setId('session-2');

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

    /**
     * @param list<array{
     *     id: int,
     *     ai_session_id: string,
     *     created_at: string,
     *     log_format: string|null,
     *     return_code: int|null,
     *     error_message: string|null
     * }> $sessionLogRows
     * @param list<array{
     *     id: int,
     *     target_ai_session_id: string,
     *     status: string|null
     * }> $moderationEventRows
     * @return SessionSummariser
     */
    private function createSummariserWithRows(array $sessionLogRows, array $moderationEventRows): SessionSummariser
    {
        $logConnector = new MockConnector($this);
        $logConnector->setNextFetchAll($sessionLogRows);

        $moderationConnector = new MockConnector($this);
        $moderationConnector->setNextFetchAll($moderationEventRows);

        $database = new class ($this, [$logConnector, $moderationConnector]) extends MockConnector {
            /**
             * @param TestCase $testCase
             * @param list<MockConnector> $connectors
             */
            public function __construct(TestCase $testCase, private array $connectors)
            {
                parent::__construct($testCase);
            }

            /**
             * @inheritdoc
             */
            public function prepare(string $statement, array $driverOptions = []): PDOStatement
            {
                $connector = array_shift($this->connectors);

                if (null === $connector) {
                    throw new \RuntimeException('Unexpected extra database query in SessionSummariser test.');
                }

                return $connector->prepare($statement, $driverOptions);
            }
        };

        return new SessionSummariser($database);
    }
}
