#!/usr/bin/php
<?php

/**
 * Search for triggers that match the given pattern(s), and delete them.
 *
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Redis;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{
    /** @var string|null $instanceId */
    private ?string $instanceId;

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h','r'];
    }

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help', 'action:', 'mapper:', 'model:', 'id:', 'start:', 'end:', 'instance-id:', 'output-hash'];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        $this->instanceId = $this->getOpt('instance-id:');
        if (empty($this->instanceId)) {
            $this->instanceId = INSTANCE_ID;
        }


        $regexMode = $this->getOpt('r');
        $args = [];

        if ($this->getOpt('action:')) {
            $args['action'] = $this->getOpt('action:');
        }

        if ($this->getOpt('mapper:')) {
            $args['mapper'] = $this->getOpt('mapper:');
        }

        if ($this->getOpt('model:')) {
            $args['model'] = $this->getOpt('model:');
        }

        if ($this->getOpt('id:')) {
            $args['id'] = $this->getOpt('id:');
        }

        if ($this->getOpt('start:')) {
            $args['start'] = strtotime($this->getOpt('start:'));
        }

        if ($this->getOpt('end:')) {
            $args['end'] = strtotime($this->getOpt('end:'));
        }

        if (!count($args)) {
            $this->getLogger()->error("Insufficient search parameters specified! Try -h for more information and help.");
            exit(1);
        }

        $this->checkRedis($args, $regexMode);
    }


    /**
     * @param array $args
     * @param bool $regexMode
     */
    private function checkRedis($args, $regexMode): void
    {
        $outputHash = $this->getOpt('output-hash');
        $redis = Redis::getStorageClientInstance();
        $decorator = new Decorator();

        if (!$redis) {
            $this->getLogger()->critical("Redis instance not available");
            exit(1);
        }

        // get a list of all available redis messages o.O;
        $redisList = $redis->keys('msg:' . $this->instanceId . ':s:*');

        $this->getLogger()->info("Checking redis data...");
        $acks = 0;

        foreach ($redisList as $key) {
            $this->getLogger()->debug("Checking key: $key");
            $compressed = $redis->get($key);
            $uncompress = gzuncompress($compressed);
            $msg        = unserialize($uncompress);
            if ($msg instanceof TriggerInterface) {
                $event = $msg->getEvent();
                if ($event instanceof CrudEventInterface) {
                    $action      = $event->getAction();
                    $mapperClass = get_class($event->getMapper());
                    $modelClass  = get_class($event->getObject());
                    $id          = $event->getMapper()->getModelProperty($event->getObject(), $event->getMapper()->getIdentifier());

                    // Is this output?
                }
                $msg->getTimestamp();
            } else {
                $this->getLogger()->warning(sprintf("Key %s does not contain a %s instance: skipping", $key, TriggerInterface::class));
            }
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
