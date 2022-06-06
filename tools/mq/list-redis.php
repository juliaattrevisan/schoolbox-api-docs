#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Alaress\Scheduler\Trigger\CancelTrigger;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Lists all notification triggers and messages in human readable way.
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

        $this->redis = $this->getDi()->get('redis.storage');

        // non-blocking search of the keyspace for all messages
        $search = new Keyspace($this->redis, "msg:{$this->instanceId}:*");
        $idx = 0;
        foreach ($search as $key) {
            $this->getLogger()->info('=====================================================================');
            $readableTTL = self::secondsToTime($this->redis->ttl($key));
            $expiryTime = self::ttlToDate($this->redis->ttl($key));
            $this->getLogger()->info("[$idx] $key : ({$this->redis->ttl($key)}{$readableTTL} : {$expiryTime})");

            $expiryTime = self::ttlToDate($this->redis->ttl($key));
            $this->getLogger()->info("[ $key : (TTL: {$expiryTime}) ]");

            $keyType = $this->redis->type($key);
            $uncompressed = null;
            try {
                $rawRedisData = $this->redis->get($key);
                $uncompressed = gzuncompress($rawRedisData);
                $instance = unserialize($uncompressed);
            } catch (Exception $e) {
                if ($keyType->getPayload() === 'set') {
                    $setCount = \count($this->redis->smembers($key));
                    $this->getLogger()->info("{$setCount} messages exist.");

                    // list messages in this set
                    foreach ($this->redis->smembers($key) as $memberKey) {
                        $memberExpTime = self::secondsToTime($this->redis->ttl($memberKey));
                        $memberReadableTTL = self::ttlToDate($this->redis->ttl($memberKey));
                        if ($memberReadableTTL === self::TTL_NOT_SET) {
                            $this->getLogger()->warning("       $memberKey : ({$this->redis->ttl($memberKey)}{$memberExpTime} : {$memberReadableTTL})");
                        } else {
                            $this->getLogger()->info("       $memberKey : ({$this->redis->ttl($memberKey)}{$memberExpTime} : {$memberReadableTTL})");
                        }
                        $memberRawRedisData = $this->redis->get($memberKey);
                        if (!$memberRawRedisData) {
                            continue;
                        }
                        try {
                            $memberInstance = unserialize(gzuncompress($memberRawRedisData));
                        } catch (Exception $e) {
                            // probably expired
                            continue;
                        }
                        $this->extractInstance($memberInstance);
                    }
                } elseif ($uncompressed) {
                    $this->getLogger()->info("Failed to unserialize {$key} : {$e->getMessage()}");
                } else {
                    $this->getLogger()->info("Type of {$key} is {$keyType}");
                }
                continue;
            }
            $this->extractInstance($instance);
            $idx++;
        }
    }


    /**
     * Extracts instance
     * @param CompressedTrigger|CancelTrigger|Alaress\Async\Notification\Instance\Instance $instance
     */
    private function extractInstance($instance): void
    {
        if (!$instance) {
            return;
        }
        $userMapper = \Schoolbox\UserMapper::getInstance();

        if ($instance instanceof CompressedTrigger) {
            $this->getLogger()->info("CompressedTrigger");
            try {
                $output = $instance->extractTrigger();
                $classname = get_class($output->getEvent()->getObject());
                $object = $output->getEvent()->getObject();
                if (method_exists($instance->extractTrigger(), 'getModuleNotifier')) {
                    $mapper = $instance->getModuleNotifier()->getMapper();
                    if ($mapper->getIdentifier()) {
                        $getIdMethod = $mapper->getGetIdentifierMethodName();
                        $withId = "with ID {$object->$getIdMethod()}";
                    } else {
                        $withId = '(which does not have an identifier)';
                    }
                }
                if (isset($withId)) {
                    $this->getLogger()->info("Notify {$output->getLabel()}. Object {$classname} {$withId} {$output->getEvent()->getAction()}");
                } else {
                    $this->getLogger()->warning("Notify {$output->getLabel()}. Object {$classname} {$output->getEvent()->getAction()}");
                }
            } catch (Exception $e) {
                $this->getLogger()->warning($e->getMessage());
            }
        } elseif ($instance instanceof CancelTrigger) {
            $this->getLogger()->info("CancelTrigger");
            $this->getLogger()->info($instance->getLabel());
            if ($instance->getTimestamp()) {
                $this->getLogger()->info('Trigger Date:' . date('Y-m-d H:i:s', $instance->getTimestamp()));
            }
        } elseif ($instance instanceof Alaress\Async\Notification\Instance\Instance) {
            $output = $instance->jsonSerialize();
            $this->getLogger()->info('Trigger Date:' . date('Y-m-d H:i:s', $output['time']));
            foreach ($output['topics'] as $topic) {
                $this->getLogger()->info($topic);
            }
            $message = $output['title']['text'];
            foreach ($output['title']['params'] as $paramName => $paramData) {
                /** @var \Alaress\Async\Notification\Parameter\ParameterInterface $paramData */
                $message = str_replace(":{$paramName}", "[{$paramData->getText()}]", $message);
            }
            $this->getLogger()->info($message);
        }
    }

    /**
     * Converts seconds to readable date time.
     * @param int $seconds
     * @return string
     */
    private static function secondsToTime($seconds): string
    {
        if (!$seconds || $seconds < 1) {
            return '';
        }
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");
        return $dtF->diff($dtT)->format(': %a days, %h hours, %i minutes and %s seconds');
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
}, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
