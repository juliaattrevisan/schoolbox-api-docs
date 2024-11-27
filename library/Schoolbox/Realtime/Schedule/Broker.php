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
use Alaress\Sentry\Tracing\Tracer;
use Predis\ClientInterface;

/**
 * Queue broker for the scheduled message queue.
 */
class Broker extends StompRedis\Broker
{
    /**
     * The name of the queue.
     */
    public const QUEUE = 'sb.schedule';

    /**
     * Constructor: builds a Stomp+Redis queue pointing to `self::QUEUE`.
     *
     * @param string          $instanceId
     * @param AMQPInterface   $amqp
     * @param ClientInterface $redis
     * @param IClient|null    $stomp
     * @param Tracer|null     $tracer
     */
    public function __construct(
        string $instanceId,
        AMQPInterface $amqp,
        ClientInterface $redis,
        ?IClient $stomp = null,
        ?Tracer $tracer = null,
    ) {
        parent::__construct(self::QUEUE, $instanceId, $amqp, $redis, $stomp, $tracer);
    }
}
