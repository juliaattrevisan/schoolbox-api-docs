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
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This script is to add existing notification messages to an object queue, so that when a plan or news article
 * is deleted, all the related messages can be deleted. [SA-8186]
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
        $this->getLogger()->info('Starting add-msg-to-object-queue');
        $this->instanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID);
        if (empty($this->instanceId)) {
            $this->instanceId = INSTANCE_ID;
        }

        $this->redis = $this->getDi()->get('redis.storage');
        $newsMapper = \Schoolbox\Comms\News\Mapper::getInstance();
        $planMapper = Schoolbox\Assessment\Participant\Plan\Mapper::getInstance();

        // non-blocking search of the keyspace for all messages
        $search = new Keyspace($this->redis, "msg:{$this->instanceId}:*");
        foreach ($search as $key) {
            try {
                $rawRedisData = $this->redis->get($key);
                $instance = unserialize(gzuncompress($rawRedisData));
            } catch (Exception $e) {
                // probably message set
                continue;
            }

            if (!$instance instanceof Instance) {
                // should be CompressedTrigger or CancelTrigger
                continue;
            }

            /** @var Instance $instance */
            // Message
            $expiryTime = self::ttlToDate($this->redis->ttl($key));
            $this->getLogger()->debug("[ $key : (TTL: {$expiryTime}) ]");
            $output = $instance->jsonSerialize();
            $this->getLogger()->debug('Trigger Date: ' . date('Y-m-d H:i:s', $output['time']));

            foreach ($output['topics'] as $topic) {
                $this->getLogger()->debug($topic);
                if (preg_match('/duework\.([0-9]+)\./', $topic, $matches)) {
                    $id = (int)$matches[1];
                    try {
                        $planMapper->findById($id);
                        // insert the msg reference into this 'queue'
                        $this->addToObjectSet('plan', $id, $key);
                    } catch (Exception $e) {
                        $this->getLogger()->debug("Plan {$id} not found.");
                    }
                } elseif (preg_match('/news\.([0-9]+)\./', $topic, $matches)) {
                    $id = (int)$matches[1];
                    try {
                        $newsMapper->findById($id);
                        // insert the msg reference into this 'queue'
                        $this->addToObjectSet('news', $id, $key);
                    } catch (Exception $e) {
                        $this->getLogger()->debug("News {$id} not found.");
                    }
                }
            }
        }
        $this->getLogger()->info('Finished add-msg-to-object-queue');
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
     * Add given object to object set. This is used when retracted. Only plans and news are using this at the moment.
     * @param string $type
     * @param int $id
     * @param string $key
     */
    private function addToObjectSet(string $type, int $id, string $key): void
    {
        $eventObjectSet = "msg:{$this->instanceId}:q:%s:%s";
        $this->getLogger()->info("[ADDING] {$type} {$id} to object set.");
        if (!$this->perform()) {
            return;
        }

        switch ($type) {
            case 'plan':
                $this->redis->sadd(sprintf($eventObjectSet, \Schoolbox\Assessment\Participant\Plan\Model::class, $id), [$key]);
                break;
            case 'news':
                $this->redis->sadd(sprintf($eventObjectSet, \Schoolbox\Comms\News\Model::class, $id), [$key]);
                break;
            default:
        }
    }
}, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
