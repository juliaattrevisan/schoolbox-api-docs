<?php

/**
 * Resources (CMS), retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Connector\IConnector;
use Alaress\Redis;
use EtherpadLite\Client as EtherpadClient;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class Cms extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_CMS;

    private const string TABLE_FOLDER = 'folder';

    public function getRetentionLabel(): string
    {
        return 'Resources (CMS) Retention';
    }

    public function getStrategyName(): string
    {
        return ValidStrats::CMS;
    }

    /**
     * Load all active Etherpad pads for a homepage.
     *
     * @param int $homepageId
     * @return array<int, array<string, mixed>>
     */
    protected function getHomepagePadRecords(int $homepageId): array
    {
        $query = "
            SELECT id, pad_id
            FROM etherpad_pad
            WHERE homepage_id = :homepageId AND deleted_at IS NULL
        ";

        $statement = $this->getPreparedStatement($query);
        $statement->execute(['homepageId' => $homepageId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Perform homepage-scoped Etherpad pad cleanup.
     *
     * @param int  $homepageId
     * @param bool $deleteRedisKeys
     * @param bool $softDeleteMeta
     * @param bool $dryRun
     * @param bool $verbose
     * @return array<string, int|bool>
     */
    public function cleanupHomepagePads(int $homepageId, bool $deleteRedisKeys = false, bool $softDeleteMeta = false, bool $dryRun = true, bool $verbose = false): array
    {
        $padRows = $this->getHomepagePadRecords($homepageId);
        $this->log->info(sprintf("Found '%s' Etherpad pads for homepage %s", count($padRows), $homepageId));

        $summary = [
            'homepageId' => $homepageId,
            'padsFound' => count($padRows),
            'padsDeleted' => 0,
            'metaRowsSoftDeleted' => 0,
            'metaRowsHardDeleted' => 0,
            'redisKeysDeleted' => 0,
            'dryRun' => $dryRun,
        ];

        foreach ($padRows as $padRow) {
            $padId = (string)($padRow['pad_id'] ?? '');
            $padRecordId = isset($padRow['id']) ? (int)$padRow['id'] : 0;

            if ($padId === '' || $padRecordId <= 0) {
                $this->log->warning(sprintf('Skipping malformed Etherpad pad record for homepage %s: %s', $homepageId, json_encode($padRow)));
                continue;
            }

            if ($verbose) {
                $this->log->debug(sprintf('Processing Etherpad pad %s for homepage %s', $padId, $homepageId));
            }

            if ($dryRun) {
                $this->log->notice(sprintf("Dry-run: would delete Etherpad pad '%s' for homepage %s", $padId, $homepageId));
            } else {
                $padDeleted = $this->deletePadFromEtherpad($padId);
                if ($padDeleted) {
                    $summary['padsDeleted']++;
                    if ($softDeleteMeta) {
                        $summary['metaRowsSoftDeleted'] += $this->softDeleteEtherpadPad($padRecordId);
                    } else {
                        $summary['metaRowsHardDeleted'] += $this->hardDeleteEtherpadPad($padRecordId);
                    }
                } else {
                    $this->log->warning(sprintf("Etherpad pad '%s' could not be deleted; continuing with optional redis cleanup", $padId));
                }
            }

            if ($deleteRedisKeys) {
                $deletedKeys = $this->deleteEtherpadRedisKeys($padId, $homepageId, $dryRun);
                $summary['redisKeysDeleted'] += $deletedKeys;
            }
        }

        $this->log->notice(sprintf("Finished homepage Etherpad cleanup for %s pads on homepage %s", $summary['padsFound'], $homepageId));

        return $summary;
    }

    /**
     * Delete an Etherpad pad via the EtherpadLite API.
     */
    protected function deletePadFromEtherpad(string $padId): bool
    {
        $apiToken = (string)$this->config->get('etherpad_api_token');
        $etherpadHost = (string)$this->config->get(ConfigMapper::NAME_ETHERPAD_HOST);

        if ($apiToken === '' || $etherpadHost === '') {
            $this->log->warning('Etherpad API credentials are not configured; cannot delete pad ' . $padId);
            return false;
        }

        try {
            $client = new EtherpadClient($apiToken, $etherpadHost);
            $response = $client->deletePad($padId);
            $code = $response->getCode();
            if ($code !== null && (string)$code === '0') {
                $this->log->notice(sprintf("Deleted Etherpad pad '%s'", $padId));
                return true;
            }

            $this->log->warning(sprintf("Etherpad API returned unexpected code '%s' for pad '%s': %s", $code, $padId, $response->getMessage()));
            return false;
        } catch (\Throwable $e) {
            $this->log->warning(sprintf("Failed to delete Etherpad pad '%s': %s", $padId, $e->getMessage()));
            return false;
        }
    }

    /**
     * Soft-delete a local Etherpad metadata row.
     */
    protected function softDeleteEtherpadPad(int $id): int
    {
        $statement = $this->getPreparedStatement(
            "UPDATE etherpad_pad SET deleted_at = :deletedAt WHERE id = :id"
        );
        $statement->execute([
            'deletedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);

        return $statement->rowCount();
    }

    /**
     * Hard-delete a local Etherpad metadata row.
     */
    protected function hardDeleteEtherpadPad(int $id): int
    {
        $statement = $this->getPreparedStatement(
            "DELETE FROM etherpad_pad WHERE id = :id"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount();
    }

    /**
     * Delete remaining Redis keys related to a pad.
     */
    protected function deleteEtherpadRedisKeys(string $padId, int $homepageId, bool $dryRun): int
    {
        $redis = $this->getStorageRedisClient();
        if ($redis === null) {
            $this->log->warning(sprintf('Unable to resolve Redis storage client while cleaning up Etherpad pad %s', $padId));
            return 0;
        }

        $patterns = [
            sprintf('pad:%s', $padId),
            sprintf('pad:%s:*', $padId),
            sprintf('sessionstorage:%s', $padId),
        ];

        $deletedKeys = 0;
        foreach ($patterns as $pattern) {
            foreach (new Keyspace($redis, $pattern) as $key) {
                $this->log->debug(sprintf('Found Etherpad Redis key for deletion: %s', $key));
                $deletedKeys++;
                if (!$dryRun) {
                    $redis->del([$key]);
                }
            }
        }

        if ($deletedKeys && $dryRun) {
            $this->log->notice(sprintf('Dry-run: would remove %s Etherpad Redis keys for pad %s', $deletedKeys, $padId));
        }

        return $deletedKeys;
    }

    /**
     * Get a Redis storage client for optional Etherpad cleanup.
     */
    protected function getStorageRedisClient(): ?ClientInterface
    {
        $client = Redis::getStorageClientInstance();
        if ($client !== null) {
            return $client;
        }

        $redisUrl = (string)$this->config->get(ConfigMapper::NAME_REDIS_URL);
        if ($redisUrl === '') {
            return null;
        }

        Redis::getStorageInstance()->connect($redisUrl, null, '');

        return Redis::getStorageClientInstance();
    }

    protected function hasDataToDelete(): bool
    {
        $query = "
            SELECT COUNT(*) AS folders
            FROM " . self::TABLE_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        $toDelete = $statement->fetch();
        if (empty($toDelete['folders'])) {
            $this->log->info("Found '0' Resources (CMS) folders to delete");

            return false;
        }

        $this->log->info(sprintf("Found '%s' Resources (CMS) folders to delete", $toDelete['folders']));

        return true;
    }

    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting Resources (CMS) folders older than ' . $this->calculateDate());

        $query = "
            SELECT id
            FROM " . self::TABLE_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array{id: int|string}> $chunk
     */
    protected function deleteChunk(array $chunk): void
    {
        $requestedFolderIds = $this->normalizeIds(array_column($chunk, 'id'));

        // log the ID's we are wanting to delete, before we start checking how valid they are
        $this->log->debug(sprintf(
            "Processing Resources (CMS) chunk with folder IDs: %s",
            implode(', ', $requestedFolderIds),
        ));

        $folderIds = $this->expandFolderCascade($requestedFolderIds);
        $blockedFolderIds = $this->findBlockedFolderIds($folderIds);

        if ($blockedFolderIds !== []) {
            $this->log->warning(sprintf(
                "Skipping Resources (CMS) chunk because '%s' folders are referenced by excluded assessment/course relations: %s",
                count($blockedFolderIds),
                implode(', ', $blockedFolderIds),
            ));

            return;
        }

        $blogIds = $this->findIdsByFolder('blog', 'id', 'folder_id', $folderIds);
        $forumThreadIds = $this->findIdsByFolder('forumThread', 'threadID', 'categoryID', $folderIds);
        $pollIds = $this->findIdsByFolder('poll', 'id', 'folder_id', $folderIds);
        $wikiIds = $this->findIdsByFolder('wiki', 'wikiID', 'categoryID', $folderIds);
        $componentInstanceIds = $this->findIdsByFolder('component_instance', 'id', 'identifier', $folderIds);

        $counts = [];
        $counts['blog_comment'] = $this->deleteBlogComments($blogIds);
        $counts['blog_image'] = $this->deleteBlogImages($blogIds);
        $counts['blog'] = $this->deleteBlog($blogIds);
        $counts['forumPost'] = $this->deleteForumPosts($forumThreadIds);
        $counts['forumThread'] = $this->deleteForumThreads($forumThreadIds);
        $counts['pollLog'] = $this->deletePollLogs($pollIds);
        $counts['poll'] = $this->deletePolls($pollIds);
        $counts['wiki_unlinked'] = $this->setWikiLatestRevisionNull($wikiIds);
        $counts['wikiRevision'] = $this->deleteWikiRevisions($wikiIds);
        $counts['wiki'] = $this->deleteWikis($wikiIds);
        $counts['component_config_instance'] = $this->deleteComponentConfigInstances($componentInstanceIds);
        $counts['component_instance'] = $this->deleteComponentInstances($componentInstanceIds);
        $counts['folder_owned_rows'] = $this->deleteFolderOwnedRows($folderIds);
        $counts['folder'] = $this->deleteFolders($folderIds);

        $prefix = $this->perform ? 'Deleted' : 'Would have deleted';
        $this->log->notice(sprintf(
            "%s Resources (CMS) rows: %s",
            $prefix,
            implode(', ', array_map(
                static fn(string $table, int $count): string => sprintf("%s=%s", $table, $count),
                array_keys($counts),
                $counts,
            )),
        ));
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = array_map(static fn(int|string $id): int => (int) $id, $ids);
        $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<int, int> $rootFolderIds
     * @return array<int, int>
     */
    protected function expandFolderCascade(array $rootFolderIds): array
    {
        $pending = array_map(
            static fn(int $folderId): array => ['id' => $folderId, 'expanded' => false],
            array_reverse($this->normalizeIds($rootFolderIds)),
        );
        $visited = [];
        $folderIds = [];

        while ($pending !== []) {
            ['id' => $folderId, 'expanded' => $expanded] = array_pop($pending);

            if ($expanded) {
                $folderIds[] = $folderId;
                continue;
            }

            if (isset($visited[$folderId])) {
                continue;
            }

            $visited[$folderId] = true;
            $pending[] = ['id' => $folderId, 'expanded' => true];

            $childFolderIds = array_reverse($this->findChildFolderIds([$folderId]));
            foreach ($childFolderIds as $childFolderId) {
                if (!isset($visited[$childFolderId])) {
                    $pending[] = ['id' => $childFolderId, 'expanded' => false];
                }
            }
        }

        return array_values($folderIds);
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findChildFolderIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($parentIds, 'parent');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_FOLDER . " WHERE parent IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    protected function findBlockedFolderIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $blocked = [];
        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $params = Builder::getArrayParameters($folderIds, 'folder');

        foreach (
            [
                'assessment_folder' => 'folder_id',
                'course_implementation' => 'folder_id',
                'course_specification' => 'folder_id',
                'mv_assessment_relation' => 'folder_id',
            ] as $table => $column
        ) {
            $statement = $this->getPreparedStatement(
                "SELECT DISTINCT {$column} AS folder_id FROM {$table} WHERE {$column} IN {$placeholders}"
            );
            $statement->execute($params);
            $blocked = array_merge(
                $blocked,
                array_map(
                    static fn(array $row): int => (int) $row['folder_id'],
                    $statement->fetchAll(\PDO::FETCH_ASSOC),
                )
            );
        }

        return $this->normalizeIds($blocked);
    }

    /**
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    protected function findIdsByFolder(string $table, string $idColumn, string $folderColumn, array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $componentInstanceHomepage = \Component_Instance::MODULE_HOMEPAGE;

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            sprintf(
                'SELECT %s AS id FROM %s WHERE %s IN %s%s',
                $idColumn,
                $table,
                $folderColumn,
                $placeholders,
                $table === 'component_instance' ? " AND module = '$componentInstanceHomepage'" : '',
            )
        );
        $statement->execute(Builder::getArrayParameters($folderIds, 'folder'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param array<int, int> $ids
     */
    protected function setWikiLatestRevisionNull(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            ($this->perform ? 'UPDATE wiki SET latestRevision = NULL' : 'SELECT wiki.* FROM wiki')
            . " WHERE wikiID IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return $statement->rowCount();
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteByIds(string $table, string $column, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return $statement->rowCount();
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteBlogComments(array $ids): int
    {
        return $this->deleteByIds('blog_comment', 'blog_id', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteBlogImages(array $ids): int
    {
        return $this->deleteByIds('blog_image', 'blog_id', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteBlog(array $ids): int
    {
        return $this->deleteByIds('blog', 'id', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteForumPosts(array $ids): int
    {
        return $this->deleteByIds('forumPost', 'threadID', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteForumThreads(array $ids): int
    {
        return $this->deleteByIds('forumThread', 'threadID', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deletePollLogs(array $ids): int
    {
        return $this->deleteByIds('pollLog', 'poll_id', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deletePolls(array $ids): int
    {
        return $this->deleteByIds('poll', 'id', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteWikiRevisions(array $ids): int
    {
        return $this->deleteByIds('wikiRevision', 'wikiID', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteWikis(array $ids): int
    {
        return $this->deleteByIds('wiki', 'wikiID', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteComponentConfigInstances(array $ids): int
    {
        if (count($ids)) {
            // log the component types we are deleting... for extra debug info
            $query = $this->database->prepare(
                "
            select
                component_instance.id,
                component_definition.name,
                component_definition.class
            from component_definition
                join component_instance on component_definition.id = component_instance.component_definition_id
            where component_instance.id in " . Builder::getArrayPlaceholders($ids, 'id')
            );

            // log the component info
            $query->execute(Builder::getArrayParameters($ids, 'id'));
            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $this->log->debug(
                    sprintf(
                        "Deleting component instance '%s' with name '%s' and class '%s'",
                        $row['id'],
                        $row['name'],
                        $row['class'],
                    )
                );
            }
        } else {
            $this->log->debug("no components on folder");
        }

        return $this->deleteByIds('component_config_instance', 'component_instance_id', $ids);
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteComponentInstances(array $ids): int
    {
        return $this->deleteByIds('component_instance', 'id', $ids);
    }

    /**
     * @param array<int, int> $folderIds
     */
    protected function deleteByFolderIds(string $table, string $column, array $folderIds): int
    {
        if ($folderIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($folderIds, 'folder'));

        return $statement->rowCount();
    }

    /**
     * @param array<int, int> $folderIds
     */
    protected function deleteFolderOwnedRows(array $folderIds): int
    {
        $count = 0;
        foreach ($this->folderOwnedTables() as $table => $column) {
            $count += $this->deleteByFolderIds($table, $column, $folderIds);
        }

        return $count;
    }

    /**
     * @param array<int, int> $folderIds
     */
    protected function deleteFolders(array $folderIds): int
    {
        $rowsDeleted = 0;
        foreach ($folderIds as $folderId) {
            $rowsDeleted += $this->deleteByIds(self::TABLE_FOLDER, 'id', [$folderId]);
        }

        return $rowsDeleted;
    }

    /**
     * @return array<string, string>
     */
    private function folderOwnedTables(): array
    {
        return [
            'chat' => 'category_id',
            'chat_poll' => 'category_id',
            'etherpad_pad' => 'homepage_id',
            'event_folder' => 'folder_id',
            'file' => 'category',
            'folder_code' => 'folder_id',
            'folder_year_level' => 'folder_id',
            'google_file' => 'folder_id',
            'homepage_config' => 'cid',
            'homepage_log' => 'folder_id',
            'homepage_visits' => 'category_id',
            'link' => 'category',
            'moment_folder' => 'folder_id',
            'perms' => 'cid',
            'reportcard_comment' => 'category_id',
            'search_index' => 'folder_id',
            'socialstream_post' => 'homepage_id',
            'textbox' => 'folder_id',
            'tile_layout_for_folder' => 'folder_id',
            'userGroups' => 'cid',
        ];
    }
}
