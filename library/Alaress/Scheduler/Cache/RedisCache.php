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
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;

class RedisCache implements ICache
{
    const EXPIRY_WINDOW = 345600; // 60s * 60m * 24h * 4d

    /**
     * @param ClientInterface $redis
     * @param LoggerInterface $log
     * @param string          $instanceId
     */
    public function __construct(
        private Predis\ClientInterface $redis,
        private LoggerInterface $log,
        private string $instanceId,
    ) {
    }

    /**
     * @param Connector   $mq
     * @param string      $queueName
     * @return bool
     */
    public function restoreSchedule(Connector $mq, string $queueName): bool
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
            $this->log->debug(sprintf("Restoring %s", $itemKey));

            // Fetch from Redis
            $value = $this->redis->get($itemKey);
            if (!isset($value)) {
                $this->log->warning(sprintf("Key %s does not exist: cannot restore", $itemKey));
                continue;
            }

            // Decompress
            $uncompressedValue = gzuncompress($value);
            if ($uncompressedValue === false) {
                $this->log->warning(sprintf("Error decompressing data in key %s: cannot restore", $itemKey));
                continue;
            }

            // Unserialize
            $event = unserialize($uncompressedValue, []);
            if (!$event instanceof TriggerInterface) {
                $this->log->warning(
                    sprintf(
                        "Error unserializing data in key %s (expected %s, received %s): cannot restore",
                        $itemKey,
                        CrudEventInterface::class,
                        is_object($event) ? get_class($event) : 'non-object',
                    )
                );
                continue;
            }

            // Restore
            $context     = ["msg:{$this->instanceId}:"];
            $amqpPayload = json_encode([$itemKey, $context]);
            $mq->publish($amqpPayload, \Schoolbox\Realtime\Schedule\Broker::QUEUE, ['exchange' => '']);
            $this->log->debug(sprintf("Restoring event in key %s", $itemKey));
            ++$i;
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
    public function saveSchedule(ResolutionManager $resolvers): bool
    {
        $i = 0;

        $resolvers = $resolvers->getAllResolvers();

        if (!$resolvers->count()) {
            // nothing to save; that's a success
            return true;
        }

        // clear the cache
        $this->clearCacheSet();

        foreach ($resolvers as $resolver) {
            foreach ($resolvers->offsetGet($resolver) as $trigger) {
                /** @var TriggerInterface $trigger */

                $timestamp = date('Y-m-d H:i:s', $trigger->getTimestamp());
                $name      = "{$trigger->getEventObjectId()} - {$trigger->getLabel()} ({$timestamp})";

                // store this compressed data
                $stored = $this->storeCache($trigger);

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
    protected function fetchItemKeys(): array
    {
        return $this->redis->smembers($this->getNamespacedKey('index'));
    }


    /**
     * Delete the existing cache of data
     */
    protected function clearCacheSet(): void
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
            $deleted = $this->redis->srem($this->getNamespacedKey('index'), $itemKey);

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
     * @param TriggerInterface $trigger
     * @return bool
     */
    protected function storeCache(TriggerInterface $trigger): bool
    {
        $compressedData = gzcompress(serialize($trigger), 9);
        $hash           = sha1($compressedData);
        $itemKey        = "msg:{$this->instanceId}:s:$hash";

        // it should already
        $existingData = $this->redis->get($itemKey);
        if ($existingData) {
            $this->log->debug("Adding existing key $itemKey");
            $isset = $this->redis->sadd($this->getNamespacedKey('index'), [$itemKey]);
        } else {
            $this->log->debug("Storing new key $itemKey");
            [$isset] = $this->redis->transaction(
                function (Predis\Transaction\MultiExec $tx) use ($itemKey, $compressedData) {
                    // don't override data if it already exists (it probably doesn't matter, but hey. integrity, yo.)
                    $tx->setnx($itemKey, $compressedData)
                        // REGARDLESS of whether we just set the key, reset its TTL
                       ->expire($itemKey, self::EXPIRY_WINDOW)
                        // ensure the key is in the index
                       ->sadd($this->getNamespacedKey('index'), [$itemKey]);
                }
            );
        }
        return (bool)$isset;
    }


    /**
     * Return the given key with a full Redis namespace
     *
     * @param string $key
     * @return string
     */
    protected function getNamespacedKey(string $key): string
    {
        return $this->implodeFiltered(':', ['schedule', $this->instanceId, $key]);
    }


    /**
     * @param string $glue
     * @param array  $array
     * @return string
     */
    protected function implodeFiltered(string $glue, array $array): string
    {
        return implode($glue, array_filter($array));
    }
}
