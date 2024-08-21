<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Cache;

use Alaress\Async\Queue\RedisQueue;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Predis;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Broker;
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
     * @param Connector $mq
     * @param string    $queueName
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
            $ttl   = $this->redis->ttl($itemKey);
            if (!$value || ($ttl === RedisQueue::TTL_EXPIRED)) {
                $this->log->warning(sprintf("Key %s is no longer valid, removing ", $itemKey));
                $this->redis->srem($this->getNamespacedKey('index'), $itemKey);
                continue;
            }

            // Decompress
            $uncompressedValue = gzuncompress($value);
            if ($uncompressedValue === false) {
                $this->log->warning(sprintf("Error decompressing data in key %s: cannot restore", $itemKey));
                continue;
            }

            // Unserialize
            $trigger = unserialize($uncompressedValue, []);
            if (!$trigger instanceof TriggerInterface) {
                // TODO: this part can be removed once v23.1 is released
                if ($trigger instanceof CrudEventInterface) {
                    // delete
                    $this->redis->del($itemKey);
                    $this->log->debug(sprintf("Deleted key %s from v23.0", $itemKey));
                } else {
                    $this->log->warning(
                        sprintf(
                            "Error unserializing data in key %s (expected %s, received %s): cannot restore",
                            $itemKey,
                            TriggerInterface::class,
                            is_object($trigger) ? get_class($trigger) : 'non-object',
                        )
                    );
                }
                continue;
            }

            // Restore
            $context     = ["msg:{$this->instanceId}:"];
            $amqpPayload = json_encode([$itemKey, $context]);
            $mq->publish(
                $amqpPayload,
                Broker::QUEUE,
                [
                    'exchange'   => '',
                    'expiration' => $ttl,
                ],
            );
            $this->log->debug(sprintf("Restoring event in key %s", $itemKey));
            ++$i;
        }

        $this->log->debug("Restored $i events");

        return (bool)$i;
    }


    /**
     * @return array
     */
    protected function fetchItemKeys(): array
    {
        $namespacedKey = $this->getNamespacedKey('index');
        $this->log->debug(sprintf("Restoring keys from set: %s", $namespacedKey));
        return $this->redis->smembers($namespacedKey);
    }


    /**
     * @param TriggerInterface $trigger
     * @return bool
     */
    public function storeCache(TriggerInterface $trigger): bool
    {
        [$itemKey, $compressedData] = RedisQueue::getStorage($trigger, $this->instanceId);
        $existingData = $this->redis->get($itemKey);
        if ($existingData) {
            $this->log->debug("Back up existing key $itemKey for {$trigger->getEventObjectId()} {$trigger->getEvent()->getAction()}");
            $isset = $this->redis->sadd($this->getNamespacedKey('index'), [$itemKey]);
        } else {
            $this->log->debug("Back up new key $itemKey");
            [$isset] = $this->redis->transaction(
                function (Predis\Transaction\MultiExec $tx) use ($itemKey, $compressedData): void {
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
     * @param string $itemKey
     * @return bool
     */
    public function removeCache(string $itemKey): bool
    {
        $isset          = 0;

        // it should already
        $existingData = $this->redis->get($itemKey);
        if ($existingData) {
            $this->log->debug("Removing $itemKey from back up");
            $isset = $this->redis->srem($this->getNamespacedKey('index'), $itemKey);
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
