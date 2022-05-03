<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use DateTimeInterface;

/**
 * Serializer for request messages.
 */
class MessageSerializer
{
    /**
     * Serializes `$message` into an array format, for sending to the task
     * daemon.
     *
     * @param Message $message
     * @return string
     */
    public function serialize(Message $message): string
    {
        $return = [
            'instance' => [
                'id'         => $message->getInstanceId(),
                'hostname'   => $message->getHostname(),
                'domain'     => $message->getDomain(),
                'version'    => $message->getVersion(),
                'type'       => $message->getType(),
                'schoolcode' => $message->getSchoolCode(),
            ],
            'task'     => [
                'type'     => $message->getTask(),
                // Need to verify all params are scalar,
                // and that params are an object
                'params'   => (object)$message->getParams(),
                'callback' => (object)[],
            ],
            'timestamp' => $message->getTimestamp()->format(DateTimeInterface::RFC3339),
        ];

        $callback = $message->getCallbackUrl();
        if (isset($callback)) {
            $return['task']['callback'] = [
                'url'    => $callback,
                'params' => $message->getCallbackParams(),
                'token'  => $message->getCallbackToken(),
            ];
        }

        return json_encode($return);
    }
}
