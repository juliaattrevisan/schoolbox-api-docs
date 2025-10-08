<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Payload;

use Schoolbox\User;

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
     * @param string $albumName
     * @param User   $viewer
     * @return array
     */
    public function build(string $eventName, string $albumName, User $viewer): array
    {
        /* TODO consolidate ext_user_id into a codec, for consistent representation
         */
        return [
            'event_name' => trim($eventName),
            'album_name' => trim($albumName),
            'ext_user_id' => INSTANCE_ID . '.' . $viewer->getId(),
        ];
    }
}
