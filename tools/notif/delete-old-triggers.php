#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Async\Notification\Instance\Instance;
use Monolog\Logger;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Predis\Command\RawCommand;
use Predis\Response\ServerException;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App
{
    private const TTL_NOT_SET = -1;

    private const OPT_LONG_INSTANCE_ID = 'instance-id:';

    /** @var ClientInterface $redis */
    private $redis;

    /** @var bool Use Unlink .. falling back to use del, if failed to execute Unlink */
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
        // TODO: remove this line - INSTANCE_ID + 065d71c5
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID);
        if (empty($instanceId)) {
            $instanceId = INSTANCE_ID;
        }

        $this->redis = $this->getDi()->get('redis');
        $newsMapper = \Schoolbox\Comms\News\Mapper::getInstance();
        $planMapper = Schoolbox\Assessment\Participant\Plan\Mapper::getInstance();

        $eventObjectSet = "msg:{$instanceId}:q:%s:%s";

        // non-blocking search of the keyspace for all messages
//        $search = new Keyspace($redis, 'msg:' . INSTANCE_ID . '*', 50);
        $search = new Keyspace($this->redis, "msg:{$instanceId}*");
        foreach ($search as $key) {

            $expiryTime = self::ttlToDate($this->redis->ttl($key));
            $this->getLogger()->info("[ $key : (TTL: {$expiryTime}) ]");

            /** @var Instance $instance */
            try {
                $keytype = $this->redis->type($key);
                $rawRedisData = $this->redis->get($key);
                $instance = unserialize(gzuncompress($rawRedisData));
            } catch (Exception $e) {
                $this->getLogger()->warning("[SKIPPING] {$keytype} : {$key}");
                continue;
            }

            if ($instance instanceof \Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger) {
                // CompressedTrigger
                try {
                    $output = $instance->extractTrigger();
                    $this->getLogger()->info(self::triggerInfo($output, $instance));
                    $triggerDate = new \Alaress_Date_Format($output->getTimestamp(), 'U');
                    $expireTime = (clone $triggerDate)->addDays(14);

                    // old triggers
                    if ($expireTime->isPast()) {
                        $this->deleteFromRedis('old CompressedTrigger', $triggerDate, $key);
                        continue;
                    }

                    // triggers without ttl
                    if ($expiryTime === self::TTL_NOT_SET) {
                        // add 2 weeks to the date in the msg payload
                        $this->setTTL('CompressedTrigger', $triggerDate, $key);
                    }

                } catch (Exception $e) {
                    $this->deleteFromRedis('CompressedTrigger for deleted object', null, $key);
                    continue;
                }
            } else if ($instance instanceof \Alaress\Scheduler\Trigger\CancelTrigger) {
                // CancelTrigger
                $this->deleteFromRedis('CancelTrigger', null, $key);
            } else {
                // Message
                $output = $instance->jsonSerialize();
                $this->getLogger()->info(date('Y-m-d H:i:s', $output['time']));
                $triggerDate = new Alaress_Date_Format($output['time'], 'U');

                foreach ($output['topics'] as $topic) {
                    $this->getLogger()->info($topic);
                    if (preg_match('/duework\.([0-9]+)\./', $topic, $matches)) {
                        $id = $matches[1];
                        try {
                            $planMapper->findById($id);
                            // insert the msg reference into this 'queue'
                            $this->redis->sadd(sprintf($eventObjectSet, get_class(new \Schoolbox\Assessment\Participant\Plan\Model()), $id), [$key]);
                        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                            $this->getLogger()->warning("Plan {$id} is deleted.");
                            $this->deleteFromRedis("Message for plan {$id}", $triggerDate, $key);
                        } catch (Exception $e) {
                            $this->getLogger()->warning("Plan {$id} not found.");
                            $this->deleteFromRedis("Message for plan {$id}", $triggerDate, $key);
                        }
                    } elseif (preg_match('/news\.([0-9]+)\./', $topic, $matches)) {
                        $id = $matches[1];
                        try {
                            $newsMapper->findById($id);
                            // insert the msg reference into this 'queue'
                            $this->redis->sadd(sprintf($eventObjectSet, get_class(new \Schoolbox\Comms\News\Model()), $id), [$key]);
                        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                            $this->getLogger()->warning("News {$id} is deleted.");
                            $this->deleteFromRedis("Message for news {$id}", $triggerDate, $key);
                        } catch (Exception $e) {
                            $this->getLogger()->warning("News {$id} not found.");
                            $this->deleteFromRedis("Message for news {$id}", $triggerDate, $key);
                        }
                    }
                }
            }
        }
    }


    /**
     * Displays ttl in human readable format
     * @param $ttl
     * @return false|int|string
     */
    private static function ttlToDate($ttl)
    {
        switch ($ttl) {
            case -1:
                return -1;
            case -2:
                return "expired";
            default:
                return date('Y-m-d H:i:s', time() + $ttl);
        }
    }


    /**
     * Extracts compressed trigger info
     * @param \Alaress\Scheduler\Trigger\TriggerInterface $output
     * @param \Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger $instance
     * @return string
     */
    private static function triggerInfo(\Alaress\Scheduler\Trigger\TriggerInterface $output,
                                        \Schoolbox\Realtime\Schedule\Trigger\Compressed\CompressedTrigger $instance): string
    {
        $classname = get_class($output->getEvent()->getObject());
        $object = $output->getEvent()->getObject();
        $withId = '';
        if (method_exists($output, "getModuleNotifier")) {
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
     * @param Alaress_Date_Format $triggerDate
     * @param string $key
     */
    private function deleteFromRedis(string $target, ?Alaress_Date_Format $triggerDate, string $key): void
    {
        $this->getLogger()->alert("[DELETING] {$target} " . ($triggerDate ? date('Y-m-d H:i:s', $triggerDate->getUnixTimeStamp()) : ''));
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
     * @param string $target
     * @param Alaress_Date_Format|null $triggerDate
     * @param string $key
     */
    private function setTTL(string $target, ?Alaress_Date_Format $triggerDate, string $key): void
    {
        $expireTime = (clone $triggerDate)->addDays(14);
        $this->getLogger()->warning("[SETTING] {$target} TTL " . date('Y-m-d H:i:s', $expireTime->getUnixTimeStamp()));
        // If dry run
        if (!$this->perform()) {
            return;
        }
        $this->redis->expireat($key, $expireTime->getUnixTimeStamp());
    }


}, false, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
