<?php

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

/**
 * Imports Redis notification data from the _redis_migration MariaDB staging table into the
 * cloud Redis storage instance, re-keying all keys from the source instance ID to the cloud
 * instance ID.
 *
 * Run on the cloud instance after the SQL backup has been restored into RDS.
 *
 */
class Import extends CliTool\App
{
    private const OPT_SHORT_HELP      = 'h';
    private const OPT_LONG_HELP       = 'help';
    private const OPT_LONG_TARGET     = 'target-instance-id:';
    private const OPT_LONG_DROP_TABLE = 'drop-table';

    private const STAGING_TABLE = '_redis_migration';

    /** @var ClientInterface */
    private ClientInterface $redis;

    private int $keysRestored        = 0;
    private int $keysSkippedExpired  = 0;
    private int $keysSkippedExisting = 0;

    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_TARGET,
            self::OPT_LONG_DROP_TABLE,
        ];
    }

    /** @inheritdoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $targetInstanceId = $this->getOpt(self::OPT_LONG_TARGET) ?: (defined('INSTANCE_ID') ? INSTANCE_ID : null);
        if (!$targetInstanceId) {
            $this->getLogger()->error('Unable to determine target instance ID. Provide --target-instance-id.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->getLogger()->notice("Importing Redis notification data into instance: {$targetInstanceId}");
        if (!$this->perform()) {
            $this->getLogger()->notice('Dry-run mode: use -p / --perform to make changes');
        }

        if (!$this->stagingTableExists()) {
            $this->getLogger()->error(
                sprintf(
                    'Staging table `%s` not found. Has the export been run and the SQL backup restored?',
                    self::STAGING_TABLE,
                )
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->redis = $this->getDi()->get('redis.storage');

        $this->importKeys($targetInstanceId);

        $this->getLogger()->notice(
            sprintf(
                '%s%d key(s) restored, %d skipped (expired), %d skipped (already existed in Redis)',
                $this->perform() ? '' : '[DRY-RUN] Would restore: ',
                $this->keysRestored,
                $this->keysSkippedExpired,
                $this->keysSkippedExisting,
            )
        );

        if ($this->getOpt(self::OPT_LONG_DROP_TABLE)) {
            $this->getLogger()->notice(sprintf('Dropping staging table `%s`', self::STAGING_TABLE));
            if ($this->perform()) {
                $this->getDatabaseInternal()->execute('DROP TABLE IF EXISTS `' . self::STAGING_TABLE . '`');
            }
        }
    }

    /**
     * Read all rows from the staging table in chunks, group by key, and write to Redis.
     *
     * Keyset pagination on the auto-increment primary key keeps memory usage bounded to
     * one chunk at a time rather than materialising all LONGBLOB payloads at once.
     * The export inserts all rows for each key consecutively, so a key's rows are never
     * interleaved with another key's rows; any key that straddles a chunk boundary is
     * carried over and merged with the first rows of the next chunk.
     */
    private function importKeys(string $targetInstanceId): void
    {
        $chunkSize = 500;
        $lastId    = 0;
        $carry     = []; // [ redis_key => [ rows ] ] for the key straddling the last boundary

        while (true) {
            $fresh = $this->getDatabaseInternal()->executeQuery(
                'SELECT id, redis_key, redis_type, redis_value, redis_ttl_abs, source_instance_id
                 FROM `' . self::STAGING_TABLE . '`
                 WHERE id > :lastId
                 ORDER BY id
                 LIMIT ' . $chunkSize,
                ['lastId' => $lastId]
            )->fetchAll();

            $isLastPage = count($fresh) < $chunkSize; // also true when empty

            if (!empty($fresh)) {
                $lastId  = (int) end($fresh)['id'];
                $grouped = $carry;
                $carry   = [];
                foreach ($fresh as $row) {
                    $grouped[$row['redis_key']][] = $row;
                }
                // On non-final pages the last key may have more rows in the next chunk.
                if (!$isLastPage) {
                    end($grouped);
                    $lastKey = key($grouped);
                    $carry   = [$lastKey => array_pop($grouped)];
                }
            } else {
                // Empty page: flush any rows carried over from the previous chunk.
                $grouped = $carry;
                $carry   = [];
            }

            foreach ($grouped as $originalKey => $keyRows) {
                $this->importKeyRows($originalKey, $keyRows, $targetInstanceId);
            }

            if ($isLastPage) {
                break;
            }
        }
    }

    /**
     * Evaluate a single Redis key's rows and write to Redis if appropriate.
     *
     * @param array<int, array<string, mixed>> $keyRows
     */
    private function importKeyRows(string $originalKey, array $keyRows, string $targetInstanceId): void
    {
        $firstRow = $keyRows[0];
        $type     = $firstRow['redis_type'];
        $sourceId = $firstRow['source_instance_id'];
        $ttlAbs   = $firstRow['redis_ttl_abs'] !== null ? (int) $firstRow['redis_ttl_abs'] : null;

        // Skip keys that expired in transit
        if ($ttlAbs !== null && $ttlAbs <= time()) {
            $this->getLogger()->debug("Skipping expired key: {$originalKey}");
            $this->keysSkippedExpired++;
            return;
        }

        $newKey = $this->rekey($originalKey, $sourceId, $targetInstanceId);

        $this->getLogger()->debug(sprintf(
            'Restoring %s key: %s → %s (TTL: %s)',
            $type,
            $originalKey,
            $newKey,
            $ttlAbs === null ? 'none' : date('Y-m-d H:i:s', $ttlAbs),
        ));

        // Don't overwrite data that already exists in the target Redis
        if ($this->redis->exists($newKey)) {
            $this->getLogger()->warning("Key already exists in Redis, skipping: {$newKey}");
            $this->keysSkippedExisting++;
            return;
        }

        if (!$this->perform()) {
            $this->keysRestored++;
            return;
        }

        switch ($type) {
            case 'string':
                $this->restoreStringKey($newKey, $firstRow['redis_value'], $ttlAbs);
                break;

            case 'set':
                $members = array_map(
                    fn(array $row): string => $this->rekey($row['redis_value'], $sourceId, $targetInstanceId),
                    $keyRows
                );
                $this->restoreSetKey($newKey, $members, $ttlAbs);
                break;
        }

        $this->keysRestored++;
    }

    /**
     * Write a string key to Redis with optional TTL.
     */
    private function restoreStringKey(string $key, ?string $value, ?int $ttlAbs): void
    {
        $this->redis->set($key, (string)$value);
        if ($ttlAbs !== null) {
            $this->redis->expireat($key, $ttlAbs);
        }
    }

    /**
     * Write a set key to Redis with optional TTL.
     *
     * @param string[] $members
     */
    private function restoreSetKey(string $key, array $members, ?int $ttlAbs): void
    {
        if (empty($members)) {
            return;
        }
        $this->redis->sadd($key, $members);
        if ($ttlAbs !== null) {
            $this->redis->expireat($key, $ttlAbs);
        }
    }

    /**
     * Replace the source instance ID with the target instance ID within a Redis key name
     * or set member value.
     *
     */
    private function rekey(string $value, string $sourceId, string $targetId): string
    {
        if ($sourceId === $targetId) {
            return $value;
        }
        return str_replace(':' . $sourceId . ':', ':' . $targetId . ':', $value);
    }

    /**
     * Check whether the staging table exists in the database.
     */
    private function stagingTableExists(): bool
    {
        $result = $this->getDatabaseInternal()->executeQuery(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :tableName',
            ['tableName' => self::STAGING_TABLE]
        )->fetchColumn();

        return (int)$result > 0;
    }

    private function printHelp(): void
    {
        $file = $this->getToolFilename();
        fwrite(STDOUT, <<<HELP
        Usage: {$file} [OPTION]...
        Reads Redis notification data from the `_redis_migration` MariaDB staging table and
        writes it to the cloud Redis storage instance, re-keying from the source instance ID
        to the target instance ID.

        Run after the SQL backup (which includes `_redis_migration`) has been restored into RDS.

        Options:
          --target-instance-id [ID]   Cloud Schoolbox instance ID (default: current INSTANCE_ID)
          --drop-table                Drop `_redis_migration` from RDS after successful import
          -p, --perform               Apply changes (default: dry-run, no changes made)
          -h, --help                  Print this message and exit

        Example:
          {$file} --target-instance-id newxyz99                         # dry-run
          {$file} --target-instance-id newxyz99 --perform               # import
          {$file} --target-instance-id newxyz99 --drop-table --perform  # import + cleanup

        HELP);
    }
}
