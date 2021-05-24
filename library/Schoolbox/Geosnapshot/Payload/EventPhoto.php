<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Payload;

/**
 * Class EventPhoto
 *
 * This payload is specifically used to obtain pictures of a particular event
 * by sending Geosnapshot a string upon which to perform their matching
 *
 * @package Schoolbox\Geosnapshot\Payload
 */
class EventPhoto
{

    /**
     * Builds a payload for getting events' pictures matching $eventName
     *
     * @param string $eventName
     * @return array
     */
    public function build(string $eventName): array
    {
        return [
            'event_name' => trim($eventName)
        ];
    }
}
