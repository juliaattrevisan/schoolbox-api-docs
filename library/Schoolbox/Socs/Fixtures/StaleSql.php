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
     * Builds the "prepare" queries used at sync start to mark current SOCS-owned rows as stale.
     *
     * Marking first lets the import distinguish freshly synced records from leftovers that should be cleaned up.
     *
     * @param array{matchup:string,sport:string,season:string,division:string,team:string,venue:string} $tables
     * @return string[]
     */
    public static function buildPrepareQueries(array $tables, string $externalIdPrefix = Registry::EXTERNAL_ID_PREFIX, string $seasonName = Registry::SOCS_FIXTURES): array
    {
        return [
            self::buildMarkStaleByExternalId($tables['matchup'], $externalIdPrefix),
            self::buildMarkStaleByExternalId($tables['sport'], $externalIdPrefix),
            self::buildMarkStaleBySeasonName($tables['season'], $seasonName),
            self::buildMarkStaleByExternalId($tables['division'], $externalIdPrefix),
            self::buildMarkStaleByExternalId($tables['venue'], $externalIdPrefix),
            self::buildMarkStaleByExternalId($tables['team'], $externalIdPrefix),
        ];
    }

    /**
     * Builds the cleanup queries that soft-delete rows still marked stale after sync completes.
     *
     * This provides a safe two-phase stale-then-clean pattern so records only disappear when they were not refreshed.
     *
     * @param array{matchup:string,sport:string,season:string,division:string,team:string,venue:string} $tables
     * @return string[]
     */
    public static function buildCleanupQueries(array $tables, string $externalIdPrefix = Registry::EXTERNAL_ID_PREFIX, string $seasonName = Registry::SOCS_FIXTURES): array
    {
        return [
            self::buildSoftDeleteByExternalId($tables['matchup'], $externalIdPrefix),
            self::buildSoftDeleteByExternalId($tables['sport'], $externalIdPrefix),
            self::buildSoftDeleteBySeasonName($tables['season'], $seasonName),
            self::buildSoftDeleteByExternalId($tables['division'], $externalIdPrefix),
            self::buildSoftDeleteByExternalId($tables['venue'], $externalIdPrefix),
            self::buildSoftDeleteByExternalId($tables['team'], $externalIdPrefix),
        ];
    }

    /**
     * Builds hard-delete membership queries for a team before recreating relationships from SOCS payloads.
     *
     * Membership links are authoritative in the source feed, so replacing them avoids drift from incremental updates.
     *
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

    /**
     * Marks external-ID rows as stale for the current sync window.
     *
     * Fixtures use a known sentinel timestamp so unsynced records can be identified and removed in a later phase.
     */
    private static function buildMarkStaleByExternalId(string $table, string $externalIdPrefix): string
    {
        return sprintf(
            "UPDATE %s SET updated_at = '%s' WHERE external_id LIKE '%s%%'",
            $table,
            self::STALE_UPDATED_AT,
            $externalIdPrefix
        );
    }

    /**
     * Marks the SOCS season record as stale by canonical name.
     *
     * Season rows do not use the same external ID convention, so name-based matching keeps stale tracking consistent.
     */
    private static function buildMarkStaleBySeasonName(string $table, string $seasonName): string
    {
        return sprintf(
            "UPDATE %s SET updated_at = '%s' WHERE name = '%s'",
            $table,
            self::STALE_UPDATED_AT,
            $seasonName
        );
    }

    /**
     * Soft-deletes stale external-ID rows after the import has had a chance to refresh active data.
     *
     * Using soft-delete preserves auditability while removing inactive fixture records from active views.
     */
    private static function buildSoftDeleteByExternalId(string $table, string $externalIdPrefix): string
    {
        return sprintf(
            "UPDATE %s SET deleted_at = NOW() WHERE updated_at = '%s' AND external_id LIKE '%s%%'",
            $table,
            self::STALE_UPDATED_AT,
            $externalIdPrefix
        );
    }

    /**
     * Soft-deletes the stale season row matched by canonical SOCS season name.
     *
     * This mirrors external-ID cleanup logic for season data that is keyed by name in this fixture domain.
     */
    private static function buildSoftDeleteBySeasonName(string $table, string $seasonName): string
    {
        return sprintf(
            "UPDATE %s SET deleted_at = NOW() WHERE updated_at = '%s' AND name = '%s'",
            $table,
            self::STALE_UPDATED_AT,
            $seasonName
        );
    }
}
