<?php

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Alaress\Redis;
use Aws\S3\S3Client;
use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

/**
 * Exports an instance's persistent Redis notification data into a SQLite file and uploads it
 * to S3 as redis-{instanceId}.sqlite, using the instance's configured OTFS S3 credentials.
 *
 * Run on the self-hosted instance before migration to cloud.
 *
 */
class Export extends CliTool\App
{
    private const OPT_SHORT_HELP    = 'h';
    private const OPT_LONG_HELP     = 'help';
    private const OPT_LONG_INSTANCE = 'instance-id:';

    private const CREATE_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS migration (
            id                 INTEGER PRIMARY KEY AUTOINCREMENT,
            redis_key          TEXT    NOT NULL,
            redis_type         TEXT    NOT NULL,
            redis_value        BLOB,
            redis_ttl_abs      INTEGER DEFAULT NULL,
            source_instance_id TEXT    NOT NULL,
            exported_at        INTEGER NOT NULL
        )
    ";

    private ClientInterface $redis;
    private ?\PDO $sqlite = null;

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

        $sqlitePath = null;
        if ($this->perform()) {
            $sqlitePath = $this->initSqlite($instanceId);
            $this->sqlite->beginTransaction();
        }

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

        if ($this->perform()) {
            $this->sqlite->commit();
            $s3Key = "redis-{$instanceId}.sqlite";
            $this->uploadToS3($sqlitePath, $s3Key);
            unlink($sqlitePath);
        }
    }

    private function initSqlite(string $instanceId): string
    {
        $path = sys_get_temp_dir() . '/' . $instanceId . '_redis_migration.sqlite';
        $this->sqlite = new \PDO('sqlite:' . $path);
        $this->sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->sqlite->exec(self::CREATE_TABLE_SQL);
        return $path;
    }

    private function uploadToS3(string $sqlitePath, string $s3Key): void
    {
        [$client, $bucket] = $this->buildS3Client();
        $this->getLogger()->notice("Uploading to s3://{$bucket}/{$s3Key}");
        $client->putObject([
            'Bucket'     => $bucket,
            'Key'        => $s3Key,
            'SourceFile' => $sqlitePath,
        ]);
        $this->getLogger()->notice('S3 upload complete.');
    }

    /**
     * @return array{S3Client, string} [$client, $bucket]
     */
    private function buildS3Client(): array
    {
        $cfg = [
            'credentials' => [
                'key'    => trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_CRED_KEY) ?? ''),
                'secret' => trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_CRED_SECRET) ?? ''),
            ],
            'region'  => trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_REGION) ?? ''),
            'version' => trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_VERSION) ?? ''),
        ];
        $altHost   = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? '');
        $altBucket = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? '');
        $bucket    = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_BUCKET) ?? '');

        if (!empty($altHost)) {
            $cfg['endpoint']              = $altHost;
            $cfg['credentials']['key']    = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_ACCESS_KEY) ?? '');
            $cfg['credentials']['secret'] = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_SECRET_KEY) ?? '');
            $cfg['region']                = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_REGION) ?? '');
        }
        if (!empty($altHost) && !empty($altBucket)) {
            $bucket = $altBucket;
        }

        return [new S3Client($cfg), $bucket];
    }

    private const PROGRESS_INTERVAL = 1000;

    /**
     * Scan Redis and write matching keys to the SQLite file.
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
     * Export a single Redis key into the SQLite file.
     */
    private function exportKey(string $key, string $instanceId): void
    {
        $ttl = $this->redis->ttl($key);

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
        $stmt = $this->sqlite->prepare(
            'INSERT INTO migration (redis_key, redis_type, redis_value, redis_ttl_abs, source_instance_id, exported_at)
             VALUES (:key, :type, :value, :ttlAbs, :instanceId, :exportedAt)'
        );
        $stmt->execute([
            'key'        => $key,
            'type'       => $type,
            'value'      => $value,
            'ttlAbs'     => $ttlAbs,
            'instanceId' => $instanceId,
            'exportedAt' => time(),
        ]);
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

    private function printHelp(): void
    {
        $file = $this->getToolFilename();
        fwrite(STDOUT, <<<HELP
        Usage: {$file} [OPTION]...
        Exports an instance's Redis notification data to a SQLite file and uploads it to S3
        as redis-{instanceId}.sqlite, using the instance's configured OTFS S3 credentials.

        Options:
          --instance-id [ID]   Source Schoolbox instance ID (default: current INSTANCE_ID)
          -p, --perform        Apply changes (default: dry-run, no changes made)
          -h, --help           Print this message and exit

        Example:
          {$file} --instance-id abc12345           # dry-run
          {$file} --instance-id abc12345 --perform  # export and upload to S3

        HELP);
    }
}
