<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Alaress\Redis;
use GuzzleHttp\Psr7\Uri;
use Predis;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Predis\ClientInterface;
use Schoolbox\Config\ConfigMapper;

/**
 * Migrates data in a redis server to another redis server
 */
class Copy extends CliTool\App
{
    private const OPT_SHORT_HELP  = 'h';
    private const OPT_LONG_HELP   = 'help';
    private const OPT_LONG_HOST   = 'host:';
    private const OPT_LONG_PORT   = 'port:';
    private const OPT_LONG_MOVE   = 'move';
    private const OPT_LONG_DELETE = 'delete';
    private const OPT_LONG_UPDATE_CONFIG = 'update-config';

    private const REDIS_DEFAULT_PORT = 6379;
    private const TARGET_KEY         = '';

    // can be confirmed with "redis-cli Keyspace" command
    private const TARGET_DB = 0;

    // the maximum idle time in any moment of the communication with the destination instance in milliseconds
    private const MIGRATE_TIMEOUT   = 5000;
    private const MIGRATE_TYPE_COPY = 'COPY';
    private const MIGRATE_TYPE_MOVE = 'MOVE';
    private const MIGRATE_KEYS      = 'KEYS';

    /** @var ClientInterface $sourceRedis */
    private ClientInterface $sourceRedis;

    // total size of data
    private int $totalLength = 0;

    // total number of data
    private int $count = 0;

    // whether this is to delete keys
    private bool $isDelete = false;

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
        ];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HOST,
            self::OPT_LONG_PORT,
            self::OPT_LONG_MOVE,
            self::OPT_LONG_DELETE,
            self::OPT_LONG_UPDATE_CONFIG,
        ];
    }

    /**
     * @return void
     */
    private function printHelp(): void
    {
        $file = $this->getToolFilename();
        $help =
            "Usage: {$file} [OPTION]...
Copies an instance's redis data to the specified server
--help Print a help message and exit
--host [HOST] Domain name or IP address of destination redis server
--port [PORT] Port of destination redis server. Default to 6379 if not provided
--move Delete moved keys from source redis server, if provided
--delete Delete keys from source server which are already copied to destination server
--update-config Update config.redis_url to the destination redis server
--perform Without this flag, tool will run in dry-run mode
";
        fwrite(STDOUT, $help);
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if (!$this->getOpt(self::OPT_LONG_HOST)) {
            $this->getLogger()->error("Please provide destination redis server with --host");
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $this->isDelete = $this->getOpt(self::OPT_LONG_DELETE);
        $migrateType    = $this->getOpt(self::OPT_LONG_MOVE) ? self::MIGRATE_TYPE_MOVE : self::MIGRATE_TYPE_COPY;
        if (!$this->isDelete) {
            $this->getLogger()->info("Migrating with type: $migrateType");
        }

        /**
         * Prepare redis server connections
         */
        $this->sourceRedis = $this->getDi()->get('redis.storage');
        $sourceRedisUrl    = new Uri($this->getConfig()->get(ConfigMapper::NAME_REDIS_URL));

        $targetRedisUrl = (new Uri())
            ->withScheme('tcp')
            ->withHost($this->getOpt(self::OPT_LONG_HOST))
            ->withPort($this->getOpt(self::OPT_LONG_PORT) ?? self::REDIS_DEFAULT_PORT);

        if ((string)$sourceRedisUrl === (string)$targetRedisUrl) {
            $this->getLogger()->error("Destination URL $targetRedisUrl must be different from source URL $sourceRedisUrl");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $targetRedisInstance = Redis::getStorageInstance()->connect((string)$targetRedisUrl, null, INSTANCE_ID);
        $targetRedis         = $targetRedisInstance->getClient();

        // check whether both of them can be connected
        try {
            $targetRedis->getConnection()->connect();
            $this->sourceRedis->getConnection()->connect();
            // not necessary to keep connection
            $targetRedis->getConnection()->disconnect();
            $this->sourceRedis->getConnection()->disconnect();
        } catch (\Exception $e) {
            $this->getLogger()->error("Unable to connect: {$e->getMessage()}");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        // See library/Alaress/Redis/CommandLineTool/Report.php for patterns
        $keyPatterns = Registry::KEY_PATTERNS[Redis::INSTANCE_STORAGE];

        // Skipping keys which doesn't have INSTANCE_ID
        $keyPatterns = array_filter(
            $keyPatterns,
            static function (string $pattern) {
                return str_contains($pattern, INSTANCE_ID);
            }
        );

        /**
         * Prepare the migrate command
         */
        $migrate = new \Predis\Command\RawCommand('MIGRATE');
        $args    = [
            $targetRedisUrl->getHost(),
            $targetRedisUrl->getPort(),
            self::TARGET_KEY,
            self::TARGET_DB,
            self::MIGRATE_TIMEOUT,
        ];
        if ($migrateType !== self::MIGRATE_TYPE_MOVE) {
            $args[] = self::MIGRATE_TYPE_COPY;
        }
        $args[] = self::MIGRATE_KEYS;

        /**
         * Migrate keys
         */
        if ($this->isDelete) {
            $this->getLogger()->info("Start deleting keys from {$sourceRedisUrl}");
        }
        $cursor = null;
        while ($cursor !== '0') {
            [
                $cursor,
                $elements,
            ] = $this->sourceRedis->scan($cursor, [
                'MATCH' => '*',
                'COUNT' => 10000,
            ]);
            foreach ($elements as $key) {
                foreach ($keyPatterns as $pattern) {
                    if (!preg_match("/$pattern/", $key)) {
                        continue;
                    }
                    try {
                        if ($this->isDelete) {
                            if ($targetRedis->exists($key)) {
                                $this->logKey($key);
                                if ($this->perform()) {
                                    $this->sourceRedis->del($key);
                                }
                            } else {
                                $this->getLogger()->warning("Unable to find {$key} on {$targetRedisUrl}. Skipping.");
                                continue;
                            }
                        } else {
                            $this->logKey($key);
                            if ($targetRedis->exists($key)) {
                                $this->getLogger()->warning("Key {$key} already exists on {$targetRedisUrl}. Skipping.");
                                continue;
                            }
                            if ($this->perform()) {
                                $migrate->setArguments(array_merge($args, [$key]));
                                $this->sourceRedis->executeCommand($migrate);
                            }
                        }
                    } catch (\Exception $e) {
                        $this->getLogger()->warning("Failed to migrate {$key}: {$e->getMessage()}");
                    }
                }
            }
        }
        if ($this->perform()) {
            if ($this->getOpt(self::OPT_LONG_UPDATE_CONFIG)) {
                $this->getDatabaseInternal()->executeQuery(
                    "UPDATE config SET value = :newRedisUrl WHERE name = :redisConfig",
                    [
                        'newRedisUrl' => (string)$targetRedisUrl,
                        'redisConfig' => ConfigMapper::NAME_REDIS_URL,
                    ]
                );
                $this->getLogger()->info(sprintf('Config %s is updated from %s to %s.', ConfigMapper::NAME_REDIS_URL, $sourceRedisUrl, $targetRedisUrl));
            }
            $this->getLogger()->info(sprintf('%d Keys %s. Total size: %d', $this->count, $this->isDelete ? 'deleted' : 'migrated', $this->totalLength));
        } else {
            if ($this->getOpt(self::OPT_LONG_UPDATE_CONFIG)) {
                $this->getLogger()->info(sprintf('Config %s will be updated from %s to %s.', ConfigMapper::NAME_REDIS_URL, $sourceRedisUrl, $targetRedisUrl));
            }
            $this->getLogger()->info(sprintf('%d Keys will be %s. Total size: %d', $this->count, $this->isDelete ? 'deleted' : 'migrated', $this->totalLength));
        }

        $this->deprecationNotice("SA-19334");
    }

    /**
     * @param string $key
     * @return void
     */
    private function logKey(string $key)
    {
        $type = $this->sourceRedis->type($key);
        switch ($type) {
            case 'string':
                $length = $this->sourceRedis->strlen($key);
                break;
            case 'set':
                $length = array_sum(array_map('strlen', $this->sourceRedis->smembers($key)));
                break;
            case 'none':
                $length = 0;
                break;
            case 'zset':
                $length = array_sum(array_map('strlen', $this->sourceRedis->zrange($key, 0, -1)));
                break;
            default:
                $this->getLogger()->warning("Unrecognised payload: '{$type}'");
                $length = 0;
        }
        $ttl = $this->sourceRedis->ttl($key);
        $this->getLogger()->debug(sprintf('%s %s', $this->isDelete ? 'Deleting' : 'Migrating', $key));
        $this->getLogger()->debug("payload: {$type}, ttl: $ttl, length: $length");
        $this->count++;
        $this->totalLength += $length;
    }
}
