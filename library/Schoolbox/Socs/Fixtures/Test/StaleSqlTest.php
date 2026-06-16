<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Socs\Fixtures\Registry;
use Schoolbox\Socs\Fixtures\StaleSql;

#[\PHPUnit\Framework\Attributes\CoversClass(StaleSql::class)]
class StaleSqlTest extends TestCase
{
    public function testPrepareQueriesAreScopedToSocsOwnedRecords(): void
    {
        $queries = StaleSql::buildPrepareQueries([
            'matchup' => 'sportsfixtures_match',
            'sport' => 'sportsfixtures_sport',
            'season' => 'sportsfixtures_season',
            'division' => 'sportsfixtures_division',
            'team' => 'sportsfixtures_team',
            'venue' => 'sportsfixtures_venue',
        ]);

        $this->assertContains(
            "UPDATE sportsfixtures_match SET updated_at = '0000-00-00 00:00:00' WHERE external_id LIKE 'socs:%'",
            $queries
        );
        $this->assertContains(
            "UPDATE sportsfixtures_season SET updated_at = '0000-00-00 00:00:00' WHERE name = 'SOCS Fixtures'",
            $queries
        );
        $this->assertNotContains(
            "UPDATE sportsfixtures_match SET updated_at = '0000-00-00 00:00:00'",
            $queries
        );
    }

    public function testCleanupQueriesAreScopedToSocsOwnedRecords(): void
    {
        $queries = StaleSql::buildCleanupQueries([
            'matchup' => 'sportsfixtures_match',
            'sport' => 'sportsfixtures_sport',
            'season' => 'sportsfixtures_season',
            'division' => 'sportsfixtures_division',
            'team' => 'sportsfixtures_team',
            'venue' => 'sportsfixtures_venue',
        ]);

        $this->assertContains(
            "UPDATE sportsfixtures_team SET deleted_at = NOW() "
            . "WHERE updated_at = '0000-00-00 00:00:00' AND external_id LIKE 'socs:%'",
            $queries
        );
        $this->assertContains(
            "UPDATE sportsfixtures_season SET deleted_at = NOW() "
            . "WHERE updated_at = '0000-00-00 00:00:00' AND name = 'SOCS Fixtures'",
            $queries
        );
        $this->assertNotContains(
            "UPDATE sportsfixtures_team SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'",
            $queries
        );
    }

    public function testMembershipDeleteQueriesAreScopedToOneTeam(): void
    {
        $queries = StaleSql::buildTeamMembershipDeleteQueries(
            'sportsfixtures_team_user',
            'sportsfixtures_team_coach',
            123
        );

        $this->assertSame([
            'DELETE FROM sportsfixtures_team_coach WHERE team_id = 123',
            'DELETE FROM sportsfixtures_team_user WHERE team_id = 123',
        ], $queries);
    }

    public function testSocsDateFormatIsShared(): void
    {
        $this->assertSame('j M Y', Registry::SOCS_DATE_FORMAT);
    }
}
