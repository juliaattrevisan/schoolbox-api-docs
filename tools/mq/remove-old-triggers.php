#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Predis\Command\RawCommand;
use Predis\Response\ServerException;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger;
use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This script is focusing on removing old compressed triggers and cancel triggers which are no longer necessary
 * after sending out its notification messages.
 *
 * To reset TTL to existing notification messages, use reset-ttls.php
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

    /** @var bool $useUnlink Falling back to use Del command, if failed to execute Unlink */
    private $useUnlink = true;

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
        $this->getLogger()->info('Starting remove-old-triggers');

        $this->instanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID);
        if (empty($this->instanceId)) {
            $this->instanceId = INSTANCE_ID;
        }

        $this->redis = $this->getDi()->get('redis.storage');
        $numKeys = $this->redis->dbsize();
        $this->getLogger()->info("Number of keys : {$numKeys}");

        // non-blocking search of the keyspace for all messages
        $numKeysProcessed = 0;
        $search = new Keyspace($this->redis, "msg:{$this->instanceId}:*");
        foreach ($search as $key) {
            $numKeysProcessed++;
            $progress = round(($numKeysProcessed / $numKeys) * 100, 2);
            if ((int)$progress % 10 === 0) {
                $this->getLogger()->debug("{$progress} % processed");
            }

            $expiryTime = self::ttlToDate($this->redis->ttl($key));
            $this->getLogger()->debug("[ $key : (TTL: {$expiryTime}) ]");

            $keyType = $this->redis->type($key);

            // clean up set
            if ($keyType->getPayload() === 'set') {
                $members = $this->redis->smembers($key);
                $memberCount = count($members);
                foreach ($members as $member) {
                    $ttl = $this->redis->ttl($member);
                    // expired
                    if ($ttl === -2) {
                        $this->getLogger()->info("[REMOVING] expired {$member} from set: {$key} ");
                        $memberCount--;
                        if ($this->perform()) {
                            $this->redis->srem($key, $member);
                        }
                    }
                }
                // if all of the members of this set is expired, delete this set
                if ($memberCount === 0) {
                    $this->deleteFromRedis("Empty set: {$key}", null, $key);
                }
                continue;
            }

            // if the key is not string (unexpectedly), skip
            if ($keyType->getPayload() !== 'string') {
                $this->getLogger()->debug("Skipping {$key} type: {$keyType->getPayload()} ");
                continue;
            }

            $uncompressed = null;
            try {
                $rawRedisData = $this->redis->get($key);
                if (!$rawRedisData) {
                    $this->deleteFromRedis("Empty value for key: {$key}", null, $key);
                    continue;
                }
                $uncompressed = gzuncompress($rawRedisData);
                $instance = unserialize($uncompressed);
            } catch (Exception $e) {
                if ($uncompressed) {
                    // likely be corrupted data
                    $this->deleteFromRedis("Cannot unserialize: {$key}", null, $key);
                    continue;
                }
                $this->getLogger()->debug("[SKIPPING] type-{$keyType} : {$key}");
                continue;
            }

            if ($instance instanceof CompressedTrigger) {
                // CompressedTrigger
                try {
                    $output = $instance->extractTrigger();
                    $this->getLogger()->info(self::triggerInfo($output, $instance));
                    $triggerDate = \Alaress_Date_Format::newFromTimestamp((int)$output->getTimestamp());
                    $expireTime = (clone $triggerDate)->addSeconds(Instance::DEFAULT_TTL);

                    // old triggers
                    if ($expireTime->isPast()) {
                        $this->deleteFromRedis('Old CompressedTrigger', $triggerDate, $key);
                        continue;
                    }

                    // triggers without ttl
                    if ($expiryTime === self::TTL_NOT_SET) {
                        // add 2 weeks to the date in the msg payload
                        $this->setTTL('CompressedTrigger', $triggerDate, $key);
                    }
                } catch (Exception $e) {
                    $this->deleteFromRedis('CompressedTrigger for deleted object', null, $key);
                }
            } elseif ($instance instanceof CancelTrigger) {
                // CancelTrigger
                $this->deleteFromRedis('CancelTrigger', null, $key);
            }
            // The rest of the cases should be taken care by the reset-ttls.php script.
        }
        $this->getLogger()->info('Finished remove-old-triggers');
        $numKeys = $this->redis->dbsize();
        $this->getLogger()->info("Number of keys : {$numKeys}");
    }


    /**
     * Displays ttl in human readable format
     * @param int $ttl
     * @return string
     */
    private static function ttlToDate($ttl): string
    {
        switch ($ttl) {
            case -1:
                return self::TTL_NOT_SET;
            case -2:
                return "expired";
            default:
                // @phpstan-ignore-next-line: date can return false
                return date('Y-m-d H:i:s', time() + $ttl) ?: '';
        }
    }


    /**
     * Extracts compressed trigger info
     * @param TriggerInterface $output
     * @param CompressedTrigger $instance
     * @return string
     */
    private static function triggerInfo(
        TriggerInterface $output,
        CompressedTrigger $instance
    ): string {
        $object = $output->getEvent()->getObject();
        $classname = '';
        $withId = '';

        if ($object && method_exists($output, "getModuleNotifier")) {
            $classname = get_class($object);
            $mapper = $instance->getModuleNotifier()->getMapper();
            if ($mapper->getIdentifier()) {
                $getIdMethod = $mapper->getGetIdentifierMethodName();
                $withId = "with ID {$object->$getIdMethod()}";
            } else {
                $withId = '(which does not have an identifier)';
            }
        }
        return "CompressedTrigger [{$output->getLabel()}] : Object {$classname} {$withId} {$output->getEvent()->getAction()}";
    }

    /**
     * Deletes from redis
     * @param string $target
     * @param Alaress_Date_Format|null $triggerDate
     * @param string $key
     */
    private function deleteFromRedis(string $target, ?Alaress_Date_Format $triggerDate, string $key): void
    {
        $this->getLogger()->info("[DELETING] {$target} " . ($triggerDate ? date('Y-m-d H:i:s', $triggerDate->getUnixTimeStamp()) : ''));
        // If dry run
        if (!$this->perform()) {
            return;
        }
        if (!$this->useUnlink) {
            $this->redis->del([$key]);
            return;
        }

        try {
            $this->redis->executeCommand(new RawCommand(['UNLINK', $key]));
        } catch (ServerException $e) {
            $this->useUnlink = false;
            $this->redis->del([$key]);
        }
    }

    /**
     * Sets default ttl
     * @param string $target
     * @param Alaress_Date_Format|null $triggerDate
     * @param string $key
     */
    private function setTTL(string $target, ?Alaress_Date_Format $triggerDate, string $key): void
    {
        $expireTime = (clone $triggerDate)->addSeconds(Instance::DEFAULT_TTL);
        $this->getLogger()->info("[SETTING] {$target} TTL " . date('Y-m-d H:i:s', $expireTime->getUnixTimeStamp()));
        // If dry run
        if (!$this->perform()) {
            return;
        }
        $this->redis->expireat($key, $expireTime->getUnixTimeStamp());
    }
}, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
