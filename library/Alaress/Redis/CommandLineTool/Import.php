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

    private const PROGRESS_INTERVAL = 1000;
    private const SET_MEMBER_BATCH  = 50;

    /**
     * Stream rows from the staging table and write to Redis one key at a time.
     *
     * Rows are fetched in small chunks via keyset pagination. Set keys are written
     * incrementally — members are SADDed in batches of SET_MEMBER_BATCH as they arrive
     * and EXPIREAT is applied once the key closes — so memory stays bounded regardless
     * of how many members a single set contains.
     */
    private function importKeys(string $targetInstanceId): void
    {
        $totalKeys     = (int) $this->getDatabaseInternal()->executeQuery(
            'SELECT COUNT(DISTINCT redis_key) FROM `' . self::STAGING_TABLE . '`'
        )->fetchColumn();
        $keysProcessed = 0;
        $lastId        = 0;

        // Streaming state for the key whose rows are currently being consumed
        $streamKey      = null;
        $streamNewKey   = null;
        $streamType     = null;
        $streamTtlAbs   = null;
        $streamSourceId = null;
        $streamSkip     = false;
        $streamMembers  = []; // buffered set members awaiting SADD

        while (true) {
            $fresh = $this->getDatabaseInternal()->executeQuery(
                'SELECT id, redis_key, redis_type, redis_value, redis_ttl_abs, source_instance_id
                 FROM `' . self::STAGING_TABLE . '`
                 WHERE id > :lastId
                 ORDER BY id
                 LIMIT 500',
                ['lastId' => $lastId]
            )->fetchAll();

            $isLastPage = count($fresh) < 500;

            foreach ($fresh as $row) {
                $lastId = (int) $row['id'];

                if ($row['redis_key'] !== $streamKey) {
                    // Close the previous set key before moving on
                    if ($streamKey !== null && !$streamSkip && $streamType === 'set') {
                        $this->closeSetKey($streamNewKey, $streamMembers, $streamTtlAbs);
                        $this->keysRestored++;
                        $this->logProgress(++$keysProcessed, $totalKeys);
                    }

                    $streamKey      = $row['redis_key'];
                    $streamType     = $row['redis_type'];
                    $streamSourceId = $row['source_instance_id'];
                    $streamTtlAbs   = $row['redis_ttl_abs'] !== null ? (int) $row['redis_ttl_abs'] : null;
                    $streamNewKey   = $this->rekey($streamKey, $streamSourceId, $targetInstanceId);
                    $streamSkip     = false;
                    $streamMembers  = [];

                    if ($streamTtlAbs !== null && $streamTtlAbs <= time()) {
                        $this->getLogger()->debug("Skipping expired key: {$streamKey}");
                        $this->keysSkippedExpired++;
                        $this->logProgress(++$keysProcessed, $totalKeys);
                        $streamSkip = true;
                        continue;
                    }

                    if ($this->redis->exists($streamNewKey)) {
                        $this->getLogger()->warning("Key already exists in Redis, skipping: {$streamNewKey}");
                        $this->keysSkippedExisting++;
                        $this->logProgress(++$keysProcessed, $totalKeys);
                        $streamSkip = true;
                        continue;
                    }

                    $this->getLogger()->debug(sprintf(
                        'Restoring %s key: %s → %s (TTL: %s)',
                        $streamType,
                        $streamKey,
                        $streamNewKey,
                        $streamTtlAbs === null ? 'none' : date('Y-m-d H:i:s', $streamTtlAbs),
                    ));
                }

                if ($streamSkip) {
                    continue;
                }

                switch ($streamType) {
                    case 'string':
                        if ($this->perform()) {
                            $this->restoreStringKey($streamNewKey, $row['redis_value'], $streamTtlAbs);
                        }
                        $this->keysRestored++;
                        $this->logProgress(++$keysProcessed, $totalKeys);
                        $streamSkip = true; // single-row type; no further rows expected
                        break;

                    case 'set':
                        $streamMembers[] = $this->rekey($row['redis_value'], $streamSourceId, $targetInstanceId);
                        if (count($streamMembers) >= self::SET_MEMBER_BATCH && $this->perform()) {
                            $this->redis->sadd($streamNewKey, $streamMembers);
                            $streamMembers = [];
                        }
                        break;
                }
            }

            if ($isLastPage) {
                // Finalize the last open set key
                if ($streamKey !== null && !$streamSkip && $streamType === 'set') {
                    $this->closeSetKey($streamNewKey, $streamMembers, $streamTtlAbs);
                    $this->keysRestored++;
                }
                break;
            }
        }
    }

    /**
     * Flush any remaining buffered members and apply TTL to complete a set key write.
     *
     * @param string[] $members
     */
    private function closeSetKey(string $newKey, array $members, ?int $ttlAbs): void
    {
        if (!empty($members) && $this->perform()) {
            $this->redis->sadd($newKey, $members);
        }
        if ($ttlAbs !== null && $this->perform()) {
            $this->redis->expireat($newKey, $ttlAbs);
        }
    }

    private function logProgress(int $keysProcessed, int $totalKeys): void
    {
        if ($keysProcessed % self::PROGRESS_INTERVAL === 0) {
            $this->getLogger()->info(sprintf(
                'Progress: %d / %d keys (%d%%)',
                $keysProcessed,
                $totalKeys,
                (int) ($keysProcessed / $totalKeys * 100),
            ));
        }
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
