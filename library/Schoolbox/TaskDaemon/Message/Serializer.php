<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Message;

/**
 * Serializer for messages.
 */
class Serializer
{
    /**
     * Serializes `$message` into an array format, for sending to the task
     * daemon.
     *
     * @param Message $message
     * @return array
     */
    public function serialize(Message $message): array
    {
        $return = [
            'instance' => [
                'id'         => $message->getInstance()->getId(),
                'hostname'   => $message->getInstance()->getHostname(),
                'domain'     => $message->getInstance()->getDomain(),
                'version'    => $message->getInstance()->getVersion(),
                'type'       => $message->getInstance()->getType(),
                'schoolcode' => $message->getInstance()->getSchoolCode(),
            ],
            'task'     => [
                'type'     => $message->getTask(),
                // Need to verify all params are scalar
                'params'   => $message->getParams(),
                'callback' => null,
            ],
            'timestamp' => $message->getTimestamp()->toRfc3339String(),
        ];

        $callback = $message->getCallbackUrl();
        if (isset($callback)) {
            $return['task']['callback'] = [
                'url'   => $callback,
                'token' => $message->getCallbackToken(),
            ];
        }

        return $return;
    }
}
