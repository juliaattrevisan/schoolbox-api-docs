<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Schedule;

use Alaress\Async\Broker\StompRedis;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Stomp\IClient;
use Predis\ClientInterface;

/**
 * Queue broker for the scheduled message queue.
 */
class Broker extends StompRedis\Broker
{
    public const QUEUE = 'sb.schedule';

    public function __construct(
        string $instanceId,
        AMQPInterface $amqp,
        ClientInterface $redis,
        ?IClient $stomp = null
    ) {
        parent::__construct(self::QUEUE, $instanceId, $amqp, $redis, $stomp);
    }
}
