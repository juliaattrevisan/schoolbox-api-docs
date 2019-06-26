<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Enqueue\Amqp;

use Alaress\Async\Stomp\IAckResolver;
use Interop\Queue\Consumer;
use Interop\Queue\Message;

/**
 * Adapter for the acknowledgement resolver for frames retrieved via the AMQP
 * protocol, when accessed via the Enqueue library.
 */
class AckResolver implements IAckResolver
{
    /**
     * @var Consumer
     */
    private $consumer;


    /**
     * @var Message
     */
    private $message;


    /**
     * Constructor
     *
     * @param Consumer $consumer
     * @param Message  $message
     */
    public function __construct(Consumer $consumer, Message $message)
    {
        $this->consumer = $consumer;
        $this->message  = $message;
    }


    /**
     * @inheritDoc
     */
    public function ack(array $headers = []): void
    {
        $this->consumer->acknowledge($this->message);
    }


    /**
     * @inheritDoc
     */
    public function nack(array $headers = []): void
    {
        $this->consumer->reject($this->message);
    }
}
