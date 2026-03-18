<?php

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Alaress\Redis;
use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

/**
 * Exports an instance's persistent Redis notification data into a MariaDB staging table
 * (_redis_migration).
 *
 * Run on the self-hosted instance before taking the database backup.
 *
 */
class Export extends CliTool\App
{
    private const OPT_SHORT_HELP     = 'h';
    private const OPT_LONG_HELP      = 'help';
    private const OPT_LONG_INSTANCE  = 'instance-id:';
    private const OPT_LONG_DROP      = 'drop-existing';

    private const STAGING_TABLE = '_redis_migration';

    private const CREATE_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `" . self::STAGING_TABLE . "` (
            `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `redis_key`          VARCHAR(512)  NOT NULL,
            `redis_type`         ENUM('string','set') NOT NULL,
            `redis_value`        LONGBLOB,
            `redis_ttl_abs`      BIGINT UNSIGNED DEFAULT NULL,
            `source_instance_id` VARCHAR(64)   NOT NULL,
            `exported_at`        INT UNSIGNED  NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    /** @var ClientInterface */
    private ClientInterface $redis;

    private int $rowsInserted = 0;
    private int $keysExported = 0;
    private int $keysSkipped  = 0;

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
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_DROP,
        ];
    }

    /** @inheritdoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?: (defined('INSTANCE_ID') ? INSTANCE_ID : null);
        if (!$instanceId) {
            $this->getLogger()->error('Unable to determine instance ID. Provide --instance-id.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->getLogger()->notice("Exporting Redis notification data for instance: {$instanceId}");
        if (!$this->perform()) {
            $this->getLogger()->notice('Dry-run mode: use -p / --perform to make changes');
        }

        $this->redis = $this->getDi()->get('redis.storage');

        $this->prepareTable();
        $this->scanAndExport($instanceId);

        $this->getLogger()->notice(
            sprintf(
                '%s%d key(s) exported (%d row(s) inserted), %d key(s) skipped (expired)',
                $this->perform() ? '' : '[DRY-RUN] Would export: ',
                $this->keysExported,
                $this->rowsInserted,
                $this->keysSkipped,
            )
        );
    }

    /**
     * Drop (if requested) and create the staging table.
     */
    private function prepareTable(): void
    {
        $tableExists = $this->stagingTableExists();

        if ($tableExists && !$this->getOpt(self::OPT_LONG_DROP)) {
            $this->getLogger()->error(
                sprintf(
                    'Table `%s` already exists. Use --drop-existing to replace it.',
                    self::STAGING_TABLE,
                )
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        if ($tableExists && $this->getOpt(self::OPT_LONG_DROP)) {
            $this->getLogger()->notice(sprintf('Dropping existing `%s` table', self::STAGING_TABLE));
            if ($this->perform()) {
                $this->getDatabaseInternal()->execute('DROP TABLE IF EXISTS `' . self::STAGING_TABLE . '`');
            }
        }

        $this->getLogger()->notice(sprintf('Creating `%s` table', self::STAGING_TABLE));
        if ($this->perform()) {
            $this->getDatabaseInternal()->execute(self::CREATE_TABLE_SQL);
        }
    }

    private const PROGRESS_INTERVAL = 1000;

    /**
     * Scan Redis and write matching keys to the staging table.
     */
    private function scanAndExport(string $instanceId): void
    {
        $exportPatterns = $this->buildExportPatterns();

        $cursor = null;
        while ($cursor !== '0') {
            [
                $cursor,
                $elements,
            ] = $this->redis->scan($cursor, [
                'MATCH' => '*',
                'COUNT' => 10000,
            ]);

            foreach ($elements as $key) {
                foreach ($exportPatterns as $pattern) {
                    if (!preg_match("/{$pattern}/", $key, $matches)) {
                        continue;
                    }
                    // Skip keys belonging to a different instance
                    $keyInstanceId = $matches['instanceId'] ?? null;
                    if ($keyInstanceId !== $instanceId) {
                        continue;
                    }
                    $this->exportKey($key, $instanceId);
                    $keysProcessed = $this->keysExported + $this->keysSkipped;
                    if ($keysProcessed % self::PROGRESS_INTERVAL === 0) {
                        $this->getLogger()->info(sprintf(
                            'Progress: %d key(s) exported (%d row(s) inserted), %d skipped',
                            $this->keysExported,
                            $this->rowsInserted,
                            $this->keysSkipped,
                        ));
                    }
                    break;
                }
            }
        }
    }

    /**
     * Export a single Redis key into the staging table.
     */
    private function exportKey(string $key, string $instanceId): void
    {
        $ttl = $this->redis->ttl($key);

        // Key has already expired — skip
        if ($ttl === -2) {
            $this->getLogger()->debug("Skipping expired key: {$key}");
            $this->keysSkipped++;
            return;
        }

        $ttlAbs = $ttl === -1 ? null : time() + $ttl;
        $type   = $this->redis->type($key);

        $this->getLogger()->debug(sprintf(
            'Exporting %s key: %s (TTL: %s)',
            $type,
            $key,
            $ttlAbs === null ? 'none' : date('Y-m-d H:i:s', $ttlAbs),
        ));

        switch ($type) {
            case 'string':
                $this->insertRow($key, 'string', $this->redis->get($key), $ttlAbs, $instanceId);
                $this->keysExported++;
                break;

            case 'set':
                $members = $this->redis->smembers($key);
                foreach ($members as $member) {
                    $this->insertRow($key, 'set', $member, $ttlAbs, $instanceId);
                }
                $this->getLogger()->debug(sprintf('  → %d set member(s)', count($members)));
                $this->keysExported++;
                break;

            default:
                $this->getLogger()->warning("Unsupported Redis type '{$type}' for key: {$key} — skipping");
                $this->keysSkipped++;
        }
    }

    /**
     * Insert one row into the staging table.
     *
     * @param string      $key        Redis key name
     * @param string      $type       'string' or 'set'
     * @param string|null $value      Raw value (binary-safe) or set member string
     * @param int|null    $ttlAbs     Absolute Unix expiry timestamp, or null for no TTL
     * @param string      $instanceId Source instance ID
     */
    private function insertRow(
        string $key,
        string $type,
        ?string $value,
        ?int $ttlAbs,
        string $instanceId,
    ): void {
        $this->rowsInserted++;
        if (!$this->perform()) {
            return;
        }
        $this->getDatabaseInternal()->executeQuery(
            'INSERT INTO `' . self::STAGING_TABLE . '`
             (redis_key, redis_type, redis_value, redis_ttl_abs, source_instance_id, exported_at)
             VALUES (:key, :type, :value, :ttlAbs, :instanceId, :exportedAt)',
            [
                'key'        => $key,
                'type'       => $type,
                'value'      => $value,
                'ttlAbs'     => $ttlAbs,
                'instanceId' => $instanceId,
                'exportedAt' => time(),
            ]
        );
    }

    /**
     * Returns the subset of INSTANCE_STORAGE key patterns that represent
     * persistent notification data worth migrating to cloud.
     *
     * Included: msg:* (payloads, triggers, queues) and schedule:*:index
     * Excluded: sessions, remember-me, route cache, LTI tokens, etherpad, OIDC
     */
    private function buildExportPatterns(): array
    {
        return array_values(array_filter(
            Registry::KEY_PATTERNS[Redis::INSTANCE_STORAGE],
            static function (string $pattern): bool {
                return str_starts_with($pattern, '^msg:')
                    || $pattern === '^schedule:' . Registry::INSTANCE_ID_MATCH . ':index';
            }
        ));
    }

    /**
     * Check whether the staging table already exists in the database.
     */
    private function stagingTableExists(): bool
    {
        $result = $this->getDatabaseInternal()->executeQuery(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :tableName",
            ['tableName' => self::STAGING_TABLE]
        )->fetchColumn();

        return (int)$result > 0;
    }

    private function printHelp(): void
    {
        $file = $this->getToolFilename();
        fwrite(STDOUT, <<<HELP
        Usage: {$file} [OPTION]...
        Exports an instance's Redis notification data to the `_redis_migration` MariaDB staging
        table, so it is included in the next mariadb-dump and can be migrated to cloud.

        Options:
          --instance-id [ID]   Source Schoolbox instance ID (default: current INSTANCE_ID)
          --drop-existing      Drop and recreate `_redis_migration` if it already exists
          -p, --perform        Apply changes (default: dry-run, no changes made)
          -h, --help           Print this message and exit

        Example:
          {$file} --instance-id abc12345                   # dry-run
          {$file} --instance-id abc12345 --perform         # export
          {$file} --instance-id abc12345 --drop-existing --perform  # re-export

        HELP);
    }
}
