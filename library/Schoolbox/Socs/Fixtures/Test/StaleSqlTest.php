<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Socs\Fixtures\StaleSql;

#[CoversClass(StaleSql::class)]
class StaleSqlTest extends TestCase
{
    /**
     * @var array{matchup:string,sport:string,season:string,division:string,team:string,venue:string}
     */
    private const array TABLES = [
        'matchup' => 'matchup_table',
        'sport' => 'sport_table',
        'season' => 'season_table',
        'division' => 'division_table',
        'team' => 'team_table',
        'venue' => 'venue_table',
    ];

    public function testBuildsCoCurricularPrepareQueriesWithAnIsolatedScope(): void
    {
        $queries = StaleSql::buildPrepareQueries(
            self::TABLES,
            'socs-co-curricular:',
            'SOCS Co-Curricular',
        );

        self::assertSame(
            "UPDATE matchup_table SET updated_at = '0000-00-00 00:00:00' "
            . "WHERE external_id LIKE 'socs-co-curricular:%'",
            $queries[0],
        );
        self::assertSame(
            "UPDATE season_table SET updated_at = '0000-00-00 00:00:00' "
            . "WHERE name = 'SOCS Co-Curricular'",
            $queries[2],
        );
        self::assertStringNotContainsString("external_id LIKE 'socs:%'", implode("\n", $queries));
    }

    public function testBuildsCoCurricularCleanupQueriesWithAnIsolatedScope(): void
    {
        $queries = StaleSql::buildCleanupQueries(
            self::TABLES,
            'socs-co-curricular:',
            'SOCS Co-Curricular',
        );

        self::assertSame(
            "UPDATE matchup_table SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00' "
            . "AND external_id LIKE 'socs-co-curricular:%'",
            $queries[0],
        );
        self::assertSame(
            "UPDATE season_table SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00' "
            . "AND name = 'SOCS Co-Curricular'",
            $queries[2],
        );
        self::assertStringNotContainsString("external_id LIKE 'socs:%'", implode("\n", $queries));
    }
}
