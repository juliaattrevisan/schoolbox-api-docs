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
use Schoolbox\TaskDaemon\Request\Message;
use Schoolbox\TaskDaemon\Request\MessageSerializer;

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
     * Serializer for converting messages to a format which the task daemon
     * may understand.
     */
    private MessageSerializer $messageSerializer;

    /**
     * The name of the queue to send messages to.
     */
    private string $queueName;

    /**
     * Constructor
     *
     * @param Context           $context
     * @param string            $queueName
     * @param MessageSerializer $messageSerializer
     */
    public function __construct(Context $context, string $queueName, MessageSerializer $messageSerializer)
    {
        $this->context           = $context;
        $this->queueName         = $queueName;
        $this->messageSerializer = $messageSerializer;
    }

    /**
     * Send the message to the task daemon.
     *
     * @param Message $message The message to send
     * @return void
     * @throws Exception                   if the context's provider fails to
     *                                     send the message due to some internal
     *                                     error
     * @throws InvalidDestinationException if a client uses this method with an
     *                                     invalid destination
     * @throws InvalidMessageException     if an invalid message is specified
     */
    public function send(Message $message)
    {
        $this->context->createProducer()->send(
            $this->context->createQueue($this->queueName),
            $this->context->createMessage(json_encode($this->messageSerializer->serialize($message))),
        );
    }
}
