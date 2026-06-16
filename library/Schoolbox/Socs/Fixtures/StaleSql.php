<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

class StaleSql
{
    private const string STALE_UPDATED_AT = '0000-00-00 00:00:00';

    /**
     * @param array{matchup:string,sport:string,season:string,division:string,team:string,venue:string} $tables
     * @return string[]
     */
    public static function buildPrepareQueries(array $tables): array
    {
        return [
            self::buildMarkStaleByExternalId($tables['matchup']),
            self::buildMarkStaleByExternalId($tables['sport']),
            self::buildMarkStaleBySeasonName($tables['season']),
            self::buildMarkStaleByExternalId($tables['division']),
            self::buildMarkStaleByExternalId($tables['team']),
            self::buildMarkStaleByExternalId($tables['venue']),
        ];
    }

    /**
     * @param array{matchup:string,sport:string,season:string,division:string,team:string,venue:string} $tables
     * @return string[]
     */
    public static function buildCleanupQueries(array $tables): array
    {
        return [
            self::buildSoftDeleteByExternalId($tables['matchup']),
            self::buildSoftDeleteByExternalId($tables['sport']),
            self::buildSoftDeleteBySeasonName($tables['season']),
            self::buildSoftDeleteByExternalId($tables['division']),
            self::buildSoftDeleteByExternalId($tables['team']),
            self::buildSoftDeleteByExternalId($tables['venue']),
        ];
    }

    /**
     * @return string[]
     */
    public static function buildTeamMembershipDeleteQueries(
        string $teamPlayerTable,
        string $teamCoachTable,
        int $teamId
    ): array {
        return [
            sprintf('DELETE FROM %s WHERE team_id = %d', $teamCoachTable, $teamId),
            sprintf('DELETE FROM %s WHERE team_id = %d', $teamPlayerTable, $teamId),
        ];
    }

    private static function buildMarkStaleByExternalId(string $table): string
    {
        return sprintf(
            "UPDATE %s SET updated_at = '%s' WHERE external_id LIKE '%s%%'",
            $table,
            self::STALE_UPDATED_AT,
            Registry::EXTERNAL_ID_PREFIX
        );
    }

    private static function buildMarkStaleBySeasonName(string $table): string
    {
        return sprintf(
            "UPDATE %s SET updated_at = '%s' WHERE name = '%s'",
            $table,
            self::STALE_UPDATED_AT,
            Registry::SOCS_FIXTURES
        );
    }

    private static function buildSoftDeleteByExternalId(string $table): string
    {
        return sprintf(
            "UPDATE %s SET deleted_at = NOW() WHERE updated_at = '%s' AND external_id LIKE '%s%%'",
            $table,
            self::STALE_UPDATED_AT,
            Registry::EXTERNAL_ID_PREFIX
        );
    }

    private static function buildSoftDeleteBySeasonName(string $table): string
    {
        return sprintf(
            "UPDATE %s SET deleted_at = NOW() WHERE updated_at = '%s' AND name = '%s'",
            $table,
            self::STALE_UPDATED_AT,
            Registry::SOCS_FIXTURES
        );
    }
}
