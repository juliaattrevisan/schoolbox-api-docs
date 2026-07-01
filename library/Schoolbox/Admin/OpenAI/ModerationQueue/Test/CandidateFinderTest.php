<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\ModerationQueue\Candidate;
use Schoolbox\Admin\OpenAI\ModerationQueue\CandidateFinder;
use Schoolbox\OpenAi\Feature;

class CandidateFinderTest extends TestCase
{
    /**
     * @return void
     */
    public function testNeverModeratedSessionIsReturned(): void
    {
        $statement = new CandidateFinderStatement([
            [
                'session_id'            => 'session-never-moderated',
                'last_message_sent_at' => '2026-07-01 09:00:00',
                'last_moderated_at'    => null,
            ],
        ]);

        $candidates = $this->createFinder($statement)->find(10);

        $this->assertCount(1, $candidates);
        $this->assertContainsOnlyInstancesOf(Candidate::class, $candidates);
        $this->assertSame('session-never-moderated', $candidates[0]->getSessionId());
        $this->assertSame('2026-07-01 09:00:00', $candidates[0]->getLastMessageSentAt()->getDatabase());
        $this->assertNull($candidates[0]->getLastModeratedAt());
    }

    /**
     * @return void
     */
    public function testFindsSessionsRequiringModerationWithExpectedEligibilitySql(): void
    {
        $statement = new CandidateFinderStatement([
            [
                'session_id'            => 'session-newer-log',
                'last_message_sent_at' => '2026-07-01 10:00:00',
                'last_moderated_at'    => '2026-07-01 09:00:00',
            ],
        ]);

        $candidates = $this->createFinder($statement)->find(5, 7);

        $this->assertCount(1, $candidates);
        $this->assertSame('session-newer-log', $candidates[0]->getSessionId());
        $this->assertSame('2026-07-01 10:00:00', $candidates[0]->getLastMessageSentAt()->getDatabase());
        $this->assertSame('2026-07-01 09:00:00', $candidates[0]->getLastModeratedAt()?->getDatabase());
        $this->assertSame(['excludedFeature' => Feature::AI_MODERATION_AGENT->value], $statement->executedParams);
        $this->assertStringContainsString('`ai_session`.feature <> :excludedFeature', $statement->sql);
        $this->assertStringContainsString('last_moderated_at IS NULL', $statement->sql);
        $this->assertStringContainsString('last_message_sent_at > last_moderated_at', $statement->sql);
        $this->assertStringContainsString('ORDER BY last_message_sent_at ASC, session_id ASC', $statement->sql);
        $this->assertStringContainsString('LIMIT 5', $statement->sql);
        $this->assertStringContainsString('OFFSET 7', $statement->sql);
    }

    /**
     * @return void
     */
    public function testModeratedAfterLatestLogIsSkippedByEligibilitySql(): void
    {
        $statement = new CandidateFinderStatement([]);

        $candidates = $this->createFinder($statement)->find(10);

        $this->assertSame([], $candidates);
        $this->assertStringContainsString('last_message_sent_at > last_moderated_at', $statement->sql);
    }

    /**
     * @return void
     */
    public function testModerationAgentSessionsAreSkippedByEligibilitySql(): void
    {
        $statement = new CandidateFinderStatement([]);

        $this->createFinder($statement)->find(10);

        $this->assertSame(['excludedFeature' => Feature::AI_MODERATION_AGENT->value], $statement->executedParams);
        $this->assertStringContainsString('`ai_session`.feature <> :excludedFeature', $statement->sql);
    }

    /**
     * @return void
     */
    public function testLimitIsRespected(): void
    {
        $statement = new CandidateFinderStatement([]);

        $this->createFinder($statement)->find(2);

        $this->assertStringContainsString('LIMIT 2', $statement->sql);
    }

    /**
     * @param CandidateFinderStatement $statement
     * @return CandidateFinder
     */
    private function createFinder(CandidateFinderStatement $statement): CandidateFinder
    {
        $database = $this->createStub(IConnector::class);
        $database->method('prepare')->willReturnCallback(
            function (string $sql) use ($statement): CandidateFinderStatement {
                $statement->sql = $sql;
                return $statement;
            }
        );

        return new CandidateFinder($database);
    }
}
