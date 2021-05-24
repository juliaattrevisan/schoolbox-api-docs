<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Cache;

use Alaress\Scheduler\Stomp\ResolutionManager;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Predis;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;
use Schoolbox\Realtime\Schedule\Trigger\IPropagator;

class RedisCache implements ICache
{
    const EXPIRY_WINDOW = 345600; // 60s * 60m * 24h * 4d

    /** @var LoggerInterface */
    protected $log;

    /** @var Predis\ClientInterface */
    protected $redis;

    /** @var string */
    protected $instanceId;


    /**
     * @param Predis\ClientInterface $redisClient
     * @param LoggerInterface $logger
     * @param string $instanceId
     */
    public function __construct(Predis\ClientInterface $redisClient, LoggerInterface $logger, $instanceId = '')
    {
        $this->log = $logger;
        $this->redis = $redisClient;
        $this->instanceId = $instanceId;
    }


    /**
     * @param Connector $mq
     * @param IPropagator $propagator
     * @param string $queueName
     * @return bool
     */
    public function restoreSchedule(Connector $mq, IPropagator $propagator, string $queueName)
    {
        $this->log->notice("Restoring $queueName from Redis cache");

        // declare the queue
        $mq->declareQueue($queueName)->bindQueue($queueName, '*.' . Notif::TOPIC_APPLICATION);

        // retrieve keys
        $itemKeys = $this->fetchItemKeys();

        if (!count($itemKeys)) {
            $this->log->notice("No data to restore");
            // nothing to restore; that's a success
            return true;
        }

        // setup ready to go
        $i = 0;

        foreach ($itemKeys as $itemKey) {
            $this->log->debug("Restoring $itemKey");

            /** @var CrudEventInterface $event */
            $event = unserialize(gzuncompress($this->redis->get($itemKey)), []);

            /** @var IPropagator $propagator */
            if ($event instanceof CrudEventInterface) {
                $propagator->propagateEvent($event);

                ++$i;
            } else {
                $this->log->error("Error decompressing cached data");
            }
        }

        $this->log->debug("Restored $i events");

        return (bool)$i;
    }


    /**
     * Save the schedule held by the given ResolutionManager
     *
     * @param ResolutionManager $resolvers
     * @return bool
     */
    public function saveSchedule(ResolutionManager $resolvers)
    {
        $i = 0;

        $resolvers = $resolvers->getAllResolvers();

        if (!$resolvers->count()) {
            // nothing to save; that's a success
            return true;
        }

        // clear the cache
        $this->deleteExisting();

        foreach ($resolvers as $resolver) {
            foreach ($resolvers->offsetGet($resolver) as $trigger) {
                /** @var TriggerInterface $trigger */

                $zip = gzcompress(serialize($trigger->getEvent()), 9);
                $hash = sha1($zip); // this might not be...erm..good.
                $size = strlen($zip);
                $timestamp = date('Y-m-d H:i:s', $trigger->getTimestamp());
                $name = "{$trigger->getEventObjectId()} - {$trigger->getLabel()} ({$timestamp}) as {$this->getNamespacedKey($hash)} [$size bytes]";

                // store this compressed data
                $stored = $this->storeCache($hash, $zip);

                if ($stored) {
                    $this->log->debug("Cached $name");
                    $i++;
                } else {
                    $this->log->notice("Event $name already exists in cache, skipping", ['char' => '-']);
                }
            }
        }

        $this->log->debug("Cached $i events");

        return (bool)$i;
    }


    /**
     * @return array
     */
    protected function fetchItemKeys()
    {
        return $this->redis->smembers($this->getNamespacedKey('index'));
    }


    /**
     * Delete the existing cache of data
     */
    protected function deleteExisting()
    {
        $this->log->notice("Clearing existing {$this->getNamespacedKey('')} from redis");

        $itemKeys = $this->fetchItemKeys();

        if (!count($itemKeys)) {
            $this->log->debug("No data to delete");
            // nothing to delete
            return;
        }

        $i = 0;

        // delete all items
        foreach ($itemKeys as $itemKey) {
            $deleted = $this->redis->del($itemKey);

            if ($deleted) {
                ++$i;
            } else {
                $this->log->notice("Redis error: unable to delete $itemKey", ['char' => '?']);
            }
        }

        $this->log->debug("$i items deleted");

        // clear the index
        $this->redis->del($this->getNamespacedKey('index'));
    }


    /**
     * @param string $key
     * @param string $compressedData
     * @return bool
     */
    protected function storeCache($key, $compressedData)
    {
        $itemKey = $this->getNamespacedKey($key);

        [$isset] = $this->redis->transaction(
            function (Predis\Transaction\MultiExec $tx) use ($itemKey, $compressedData) {
                // don't override data if it already exists (it probably doesn't matter, but hey. integrity, yo.)
                $tx->setnx($itemKey, $compressedData)
                    // REGARDLESS of whether we just set the key, reset its TTL
                    ->expire($itemKey, self::EXPIRY_WINDOW)
                    // ensure the key is in the index
                    ->sadd($this->getNamespacedKey('index'), $itemKey);
            }
        );
        return (bool)$isset;
    }


    /**
     * Return the given key with a full Redis namespace
     *
     * @param string $key
     * @return string
     */
    protected function getNamespacedKey($key)
    {
        return $this->implodeFiltered(':', ['schedule', $this->instanceId, $key]);
    }


    /**
     * @param string $glue
     * @param array $array
     * @return string
     */
    protected function implodeFiltered($glue, array $array)
    {
        return implode($glue, array_filter($array));
    }
}
