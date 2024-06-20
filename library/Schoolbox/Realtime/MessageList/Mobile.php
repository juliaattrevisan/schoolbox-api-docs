<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

/**
 * List for messages to be sent to mobile clients.
 *
 * This once was directly responsible for sending messages to mobile clients:
 * now, it simply adds them to a list to be processed by another process.
 *
 * This is because this list was unable to handle retries in a stable manner:
 * it's called within a worker, and sending the worker to sleep was causing it
 * to get killed unexpectedly and irrecoverably by its pool; thus causing
 * messages to be lost forever.
 */
class Mobile extends AbstractList
{
    /**
     * @return string
     */
    protected function getSuffix(): string
    {
        return 'mobile';
    }
}
