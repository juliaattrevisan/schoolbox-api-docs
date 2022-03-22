<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon;

use Interop\Queue\Context;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;

/**
 * Wrapper around task daemon message sending.
 */
class Producer
{
    /**
     * The queue context to which messages will be sent.
     */
    private Context $context;

    /**
     * The name of the queue to send messages to.
     */
    private string $queueName;

    /**
     * Constructor
     *
     * @param Context $context
     * @param string  $queueName
     */
    public function __construct(Context $context, string $queueName)
    {
        $this->context   = $context;
        $this->queueName = $queueName;
    }

    /**
     * Send the message to the task daemon.
     *
     * @param string $messageContent The content of the message to send:
     *                               currently, this is a string; later, it may
     *                               be a wrapper class.
     * @return void
     * @throws Exception                   if the context's provider fails to
     *                                     send the message due to some internal
     *                                     error
     * @throws InvalidDestinationException if a client uses this method with an
     *                                     invalid destination
     * @throws InvalidMessageException     if an invalid message is specified
     */
    public function send(string $messageContent)
    {
        $this->context->createProducer()->send(
            $this->context->createQueue($this->queueName),
            $this->context->createMessage($messageContent),
        );
    }
}
