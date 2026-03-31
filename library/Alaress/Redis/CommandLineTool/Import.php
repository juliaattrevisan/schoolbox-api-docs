<?php

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Aws\S3\S3Client;
use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

/**
 * Imports Redis notification data from a SQLite file stored in S3 into the cloud Redis storage
 * instance, re-keying all keys from the source instance ID to the cloud instance ID.
 *
 * Run on the cloud instance after the SQLite file has been uploaded by the export tool.
 *
 */
class Import extends CliTool\App
{
    private const OPT_SHORT_HELP   = 'h';
    private const OPT_LONG_HELP    = 'help';
    private const OPT_LONG_SOURCE  = 'source-instance-id:';
    private const OPT_LONG_TARGET  = 'target-instance-id:';

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
            self::OPT_LONG_SOURCE,
            self::OPT_LONG_TARGET,
        ];
    }

    /** @inheritdoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $sourceInstanceId = $this->getOpt(self::OPT_LONG_SOURCE) ?: null;
        if (!$sourceInstanceId) {
            $this->getLogger()->error('--source-instance-id is required.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
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

        $sqlitePath = $this->downloadFromS3($sourceInstanceId);

        $pdo = new \PDO('sqlite:' . $sqlitePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->redis = $this->getDi()->get('redis.storage');

        $this->importKeys($pdo, $targetInstanceId);

        $this->getLogger()->notice(
            sprintf(
                '%s%d key(s) restored, %d skipped (expired), %d skipped (already existed in Redis)',
                $this->perform() ? '' : '[DRY-RUN] Would restore: ',
                $this->keysRestored,
                $this->keysSkippedExpired,
                $this->keysSkippedExisting,
            )
        );

        unlink($sqlitePath);
    }

    private function downloadFromS3(string $sourceInstanceId): string
    {
        [$client, $bucket] = $this->buildS3Client();
        $s3Key      = "redis-{$sourceInstanceId}.sqlite";
        $localPath  = sys_get_temp_dir() . '/' . $sourceInstanceId . '_redis_migration.sqlite';

        $this->getLogger()->notice("Downloading s3://{$bucket}/{$s3Key}");
        $client->getObject([
            'Bucket' => $bucket,
            'Key'    => $s3Key,
            'SaveAs' => $localPath,
        ]);
        $this->getLogger()->notice('Download complete.');

        return $localPath;
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
    private const SET_MEMBER_BATCH  = 50;

    /**
     * Stream rows from the SQLite file and write to Redis one key at a time.
     */
    private function importKeys(\PDO $pdo, string $targetInstanceId): void
    {
        $totalKeys     = (int) $pdo->query('SELECT COUNT(DISTINCT redis_key) FROM migration')->fetchColumn();
        $keysProcessed = 0;
        $lastId        = 0;

        $streamKey      = null;
        $streamNewKey   = null;
        $streamType     = null;
        $streamTtlAbs   = null;
        $streamSourceId = null;
        $streamSkip     = false;
        $streamMembers  = [];

        while (true) {
            $stmt = $pdo->prepare(
                'SELECT id, redis_key, redis_type, redis_value, redis_ttl_abs, source_instance_id
                 FROM migration
                 WHERE id > :lastId
                 ORDER BY id
                 LIMIT 500'
            );
            $stmt->execute(['lastId' => $lastId]);
            $fresh = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $isLastPage = count($fresh) < 500;

            foreach ($fresh as $row) {
                $lastId = (int) $row['id'];

                if ($row['redis_key'] !== $streamKey) {
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
                        $streamSkip = true;
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
                if ($streamKey !== null && !$streamSkip && $streamType === 'set') {
                    $this->closeSetKey($streamNewKey, $streamMembers, $streamTtlAbs);
                    $this->keysRestored++;
                }
                break;
            }
        }
    }

    /**
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

    private function restoreStringKey(string $key, ?string $value, ?int $ttlAbs): void
    {
        $this->redis->set($key, (string)$value);
        if ($ttlAbs !== null) {
            $this->redis->expireat($key, $ttlAbs);
        }
    }

    private function rekey(string $value, string $sourceId, string $targetId): string
    {
        if ($sourceId === $targetId) {
            return $value;
        }
        return str_replace(':' . $sourceId . ':', ':' . $targetId . ':', $value);
    }

    private function printHelp(): void
    {
        $file = $this->getToolFilename();
        fwrite(STDOUT, <<<HELP
        Usage: {$file} [OPTION]...
        Downloads redis-{sourceInstanceId}.sqlite from S3 and writes the Redis notification data
        to the cloud Redis storage instance, re-keying from the source instance ID to the
        target instance ID.

        Options:
          --source-instance-id [ID]   Self-hosted Schoolbox instance ID (required)
          --target-instance-id [ID]   Cloud Schoolbox instance ID (default: current INSTANCE_ID)
          -p, --perform               Apply changes (default: dry-run, no changes made)
          -h, --help                  Print this message and exit

        Example:
          {$file} --source-instance-id oldinst                          # dry-run
          {$file} --source-instance-id oldinst --perform                 # import
          {$file} --source-instance-id oldinst --target-instance-id newxyz99 --perform

        HELP);
    }
}
