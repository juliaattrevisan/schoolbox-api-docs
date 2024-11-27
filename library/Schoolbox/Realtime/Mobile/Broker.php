<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Mobile;

use Alaress\Async\Broker\StompRedis;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Stomp\IClient;
use Predis\ClientInterface;

/**
 * Queue broker for the mobiled message queue.
 */
class Broker extends StompRedis\Broker
{
    /**
     * The name of the queue.
     */
    public const QUEUE = 'sb.mobile';

    /**
     * Constructor
     *
     * @param string          $instanceId
     * @param AMQPInterface   $amqp
     * @param ClientInterface $redis
     * @param IClient|null    $stomp
     */
    public function __construct(
        string $instanceId,
        AMQPInterface $amqp,
        ClientInterface $redis,
        ?IClient $stomp = null,
    ) {
        parent::__construct(self::QUEUE, $instanceId, $amqp, $redis, $stomp);
    }
}
