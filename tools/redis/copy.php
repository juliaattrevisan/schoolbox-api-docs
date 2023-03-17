#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Alaress\Redis;
use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 */
$kernel = new CliTool\Kernel(
       new class extends CliTool\App {
        private const OPT_SHORT_HELP = 'h';
        private const OPT_LONG_HELP  = 'help';
        private const OPT_LONG_HOST  = 'host:';
        private const OPT_LONG_PORT  = 'port:';
        private const OPT_LONG_MOVE  = 'move';

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

        private int $totalLength = 0;
        private int $count       = 0;

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
--perform Without this flag, tool will run in dry-run mode
";
            fwrite(STDOUT, $help);
        }

        /**
         * @inheritDoc
         */
        public function run(): void
        {
            if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
                $this->printHelp();
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }

            if (!$this->getOpt(self::OPT_LONG_HOST)) {
                $this->getLogger()->error("Please provide destination redis server with --host");
                $this->printHelp();
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }

            $migrateType = $this->getOpt(self::OPT_LONG_MOVE) ? self::MIGRATE_TYPE_MOVE : self::MIGRATE_TYPE_COPY;
            $this->getLogger()->info("Migrating with type: $migrateType");


// PREDIS DOC: https://squizzle.me/php/predis/doc/
// TODO: TO TEST        sudo /usr/bin/redis-server --port 7777

            // current storage
            /** @var ClientInterface $sourceRedis */
            $this->sourceRedis = $this->getDi()->get('redis.storage');
            $sourceRedisUrl    = new \Schoolbox\Url\Uri($this->getConfig()->get('redis_url'));

            $targetRedisUrl = (new \Schoolbox\Url\Uri())
                ->withScheme('tcp')
                ->withHost($this->getOpt(self::OPT_LONG_HOST))
                ->withPort($this->getOpt(self::OPT_LONG_PORT) ?? self::REDIS_DEFAULT_PORT);

            if ((string)$sourceRedisUrl === (string)$targetRedisUrl) {
                $this->getLogger()->error("Destination URL $targetRedisUrl must be different from source URL $sourceRedisUrl");
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }

            // TODO: getStorageInstance() ? or do I have to give this a new name?
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
            $keyPatterns = Redis\CommandLineTool\Report::KEY_PATTERNS[Redis::INSTANCE_STORAGE];

            // TODO: we may want to skip either pad ones as they don't have INSTANCE_ID
            $keyPatterns = array_filter($keyPatterns,
                static function (string $pattern) {
                    return str_contains($pattern, INSTANCE_ID);
                }
            );

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
                        if (preg_match("/$pattern/", $key)) {
                            if ($this->perform()) {
                                /**
                                 * Equivalent to:
                                 * $migrate = (new \Predis\Command\KeyMigrate());
                                 * $migrate->setArguments(['127.0.0.1',7777,"",0,5000,'COPY', 'KEYS',"msg:vagrantx:q:1:listed"]);
                                 * $sourceRedis->executeCommand($migrate);
                                 */
                                try {
                                    $this->sourceRedis->migrate(
                                        $targetRedisUrl->getHost(),
                                        $targetRedisUrl->getPort(),
                                        self::TARGET_KEY,
                                        self::TARGET_DB,
                                        self::MIGRATE_TIMEOUT,
                                        $migrateType,
                                        self::MIGRATE_KEYS,
                                        $key
                                    );
                                } catch (\Exception $e) {
                                    $this->getLogger()->warning("Failed to migrate {$key}: {$e->getMessage()}");
                                }
                            }
                            $this->processKey($key);
                            break;
                        }
                    }
                }
            }
            $this->getLogger()->info("{$this->totalLength} ($this->count}");
        }

        private function processKey(string $key)
        {
            /** @var Predis\Response\Status $type */
            $type = $this->sourceRedis->type($key);
            switch ($type->getPayload()) {
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
                    $this->getLogger()->warning("Unrecognised payload: '{$type->getPayload()}'");
                    $length = 0;
            }
            $ttl = $this->sourceRedis->ttl($key);
            $this->getLogger()->info($key);
            $this->getLogger()->info("payload: {$type->getPayload()}, ttl: $ttl, length: $length");
            $this->count++;
            $this->totalLength += $length;
        }
    }, [
           CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
           CliTool\Kernel::OPTION_ERRORS => E_ALL,
       ]
);

$kernel->runApp();
