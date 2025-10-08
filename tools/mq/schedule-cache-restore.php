#!/usr/bin/php
<?php

/**
 * Restore the Schedule cache, held in Redis, back into RabbitMQ
 * IF the schedule queue ALREADY exists in RabbitMQ, this script is to do nothing.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Scheduler\Cache\RedisCache;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\MQ\ConnectorFactory;
use Schoolbox\Realtime\Schedule;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    private const OPT_LONG_HELP = 'help';

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP
            Usage: tools/mq/schedule-cache-restore.php

            Restore the Schedule cache, held in Redis, back into RabbitMQ
            IF the schedule queue ALREADY exists in RabbitMQ, this script is to do nothing.

            HELP;
                exit;
        }

        // the passive part is important:
        // it means the queue won't be created if it doesn't already exist
        $mq = (new ConnectorFactory($this->getConfig()))->build(['passive' => true]);
        $q  = Schedule\Broker::QUEUE;

        try {
            $mq->declareQueue($q);

            // the queue exists. everything is ok. we are to do nothing.
            $this->getLogger()->notice("Queue '{$q}' already exists. Nothing to do.");
            exit(0);
        } catch (RuntimeException $e) {
            // the queue does NOT exist. this means we need to create it, then populate it with whatever is in Redis
            $this->getLogger()->notice("Queue '{$q}' does not exist; creating and re-populating from cache.", [ 'char' => '+' ]);

            try {
                // get the cache handler
                $jit = new RedisCache($this->getDi()->get('redis.storage'), $this->getLogger(), INSTANCE_ID);

                /** @var Connector $amqp */
                $amqp = $this->getDi()->get('amqp');

                // restore data if possible
                $restored = $jit->restoreSchedule($amqp, $q);

                if (!$restored) {
                    throw new RuntimeException("Could not read cached data");
                }

                $this->getLogger()->notice("Successfully restored Schedule queue from cache");
                exit(0);
            } catch (Exception $e) {
                $this->getLogger()->alert("Failed to restore Schedule queue from cache", ['exception' => $e]);
                exit(1);
            }
        }
    }
};

$kernel = new CliTool\Kernel($app, [
    CliTool\Kernel::OPTION_ERRORS => E_ALL
]);
$kernel->runApp();
