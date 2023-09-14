<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon;

use Alaress_Date_Format;
use Interop\Queue\Context;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Schoolbox\TaskDaemon\Message\HeaderAdapter;
use Schoolbox\TaskDaemon\Request\Message;
use Schoolbox\TaskDaemon\Request\MessageSerializer;

/**
 * Wrapper around task daemon message sending.
 */
class Producer
{
    /**
     * Constructor
     *
     * @param Context           $context           The queue context to which messages will be sent.
     * @param string            $queueName         Serializer for converting messages to a format which the task daemon
     *                                             may understand.
     * @param MessageSerializer $messageSerializer The name of the queue to send messages to.
     * @param HeaderAdapter     $headerAdapter     Adapter for setting task daemon specific headers on messages.
     */
    public function __construct(
        private Context $context,
        private string $queueName,
        private MessageSerializer $messageSerializer,
        private HeaderAdapter $headerAdapter,
    ) {
    }

    /**
     * Send the message to the task daemon.
     *
     * @param Message    $message     The message to send
     * @param float|null $delay       If set: delay send by this many seconds
     * @param int|null   $maxAttempts If set: override the max attempts by this
     *                                amount
     * @return void
     * @throws Exception                   if the context's provider fails to
     *                                     send the message due to some internal
     *                                     error
     * @throws InvalidDestinationException if a client uses this method with an
     *                                     invalid destination
     * @throws InvalidMessageException     if an invalid message is specified
     */
    public function send(
        Message $message,
        ?float $delay = null,
        ?int $maxAttempts = null,
    ): void {
        $enqueueMessage = $this->context->createMessage($this->messageSerializer->serialize($message));
        if (isset($delay)) {
            $this->headerAdapter->setDelay($enqueueMessage, $delay);
        }
        if (isset($maxAttempts)) {
            $this->headerAdapter->setMaxAttempts($enqueueMessage, $maxAttempts);
        }
        $this->context->createProducer()->send(
            $this->context->createQueue($this->queueName),
            $enqueueMessage,
        );
    }
}
