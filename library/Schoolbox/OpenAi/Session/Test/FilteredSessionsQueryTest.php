<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use Alaress\Database\Connector\NullConnector;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Quoter\Mysql\Quoter;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Mapper;

/**
 * Unit tests for the admin AI session list query.
 */
class FilteredSessionsQueryTest extends TestCase
{
    public function testFilteredSessionsRequireFirstLogInListQuery(): void
    {
        $sql = $this->capturePreparedSql(
            fn(Mapper $mapper) => $mapper->getFilteredSessions(null, null, null, null, null)
        );

        $this->assertStringContainsString('JOIN `ai_session_log` firstLog', $sql);
        $this->assertStringNotContainsString('LEFT JOIN `ai_session_log` firstLog', $sql);
    }

    public function testFilteredSessionsWithoutLimitDoesNotUseOffset(): void
    {
        $sql = $this->capturePreparedSql(
            fn(Mapper $mapper) => $mapper->getFilteredSessions(null, null, null, null, null, null, 10)
        );

        $this->assertStringNotContainsString('LIMIT :limit', $sql);
        $this->assertStringNotContainsString('OFFSET :offset', $sql);
    }

    public function testFilteredSessionCountRequiresChildLog(): void
    {
        $sql = $this->capturePreparedSql(
            fn(Mapper $mapper) => $mapper->countFilteredSessions(null, null, null, null, null)
        );

        $this->assertStringContainsString('EXISTS (', $sql);
        $this->assertStringContainsString('FROM `ai_session_log` l', $sql);
        $this->assertStringContainsString('l.ai_session_id = `ai_session`.id', $sql);
    }

    public function testKeywordFilterUsesPredicateBuilderForLogColumns(): void
    {
        $sql = $this->capturePreparedSql(
            fn(Mapper $mapper) => $mapper->getFilteredSessions('keyword', null, null, null, null)
        );

        $this->assertStringContainsString('`l`.`prompt` LIKE :l_prompt', $sql);
        $this->assertStringContainsString('`l`.`response` LIKE :l_response', $sql);
        $this->assertStringNotContainsString('l.prompt LIKE :keyword', $sql);
    }

    public function testDateFilterUsesSessionLogCreatedAt(): void
    {
        $start = new \Alaress_Date_Format('2026-06-10 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT);
        $end = new \Alaress_Date_Format('2026-06-11 23:59:59', \Alaress_Date_Format::DATABASE_FORMAT);

        $sql = $this->capturePreparedSql(
            fn(Mapper $mapper) => $mapper->getFilteredSessions(null, null, null, $start, $end)
        );

        $this->assertStringContainsString('FROM `ai_session_log` l', $sql);
        $this->assertStringContainsString('l.ai_session_id = `ai_session`.id', $sql);
        $this->assertStringContainsString('`l`.`created_at` >= :l_created_at_0', $sql);
        $this->assertStringContainsString('`l`.`created_at` <= :l_created_at_1', $sql);
        $this->assertStringNotContainsString('`ai_session`.`created_at` >=', $sql);
        $this->assertStringNotContainsString('`ai_session`.`created_at` <=', $sql);
    }

    /**
     * @param callable(Mapper): void $query
     */
    private function capturePreparedSql(callable $query): string
    {
        $connector = new class extends NullConnector {
            public ?string $preparedStatement = null;

            public function getQuoter(): IQuoter
            {
                return new Quoter();
            }

            public function prepare(string $statement, array $driverOptions = []): PDOStatement
            {
                $this->preparedStatement = $statement;
                throw new \RuntimeException('Prepared SQL captured');
            }
        };

        $mapper = new Mapper();
        $mapper->setDatabase($connector);

        try {
            $query($mapper);
        } catch (\RuntimeException $e) {
            $this->assertSame('Prepared SQL captured', $e->getMessage());
            return $connector->preparedStatement ?? '';
        }

        $this->fail('Expected mapper query to prepare SQL');
    }
}
