#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Predis\ClientInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Lists all notification route in cache.
 * It is recommended to use subscriptions.php.
 */
$kernel = new CliTool\Kernel(new class extends CliTool\App
{
    private const TTL_NOT_SET = "not set";

    /** @var string if not specified INSTANCE_ID will be used */
    private const OPT_LONG_INSTANCE_ID = 'instance-id:';

    /** @var ClientInterface $redis */
    private $redis;

    /** @var string $instanceId */
    private $instanceId;

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_INSTANCE_ID
        ];
    }


    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $this->instanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID);
        if (empty($this->instanceId)) {
            $this->instanceId = INSTANCE_ID;
        }

        $this->redis = $this->getDi()->get('redis');

        $redisKey = sprintf(ReadCache::CACHE_PATTERN, $this->instanceId);

        // non-blocking search of the keyspace for all messages
        $routeData = $this->redis->get($redisKey);
        $routeEntries = json_decode(gzuncompress($routeData), true);
        foreach ($routeEntries as $route => $userIds) {
            $this->getLogger()->debug("===============");
            $this->getLogger()->debug("$route");
            $this->getLogger()->debug(implode(',', array_keys($userIds)) . "\n");
        }
    }


}, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
