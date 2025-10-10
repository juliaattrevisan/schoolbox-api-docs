<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Metadata;

class Builder
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }


    /**
     * Builds event-specific metadata such as attendance links etc.
     *
     * @param array $credentials
     * @param array $class
     * @return array
     */
    public function build(array $credentials, array $class): array
    {
        /* Each event contains this additional metadata struct,
         * for extra info that you might need later
         */
        return [
            'links' => [
                'attendance' => $this->buildAttendanceLink($credentials, $class),
            ]
        ];
    }


    /**
     * Precompute a deeplinking url into Veracross using the ingredients provided
     *
     * @param array $credentials
     * @param array $class
     * @return string
     */
    private function buildAttendanceLink(array $credentials, array $class): string
    {
        return sprintf(
            'https://portals.veracross.com/%s/%s/class/%s/attendance',
            $credentials['schoolroute'],
            $credentials['portalaccessname'],
            /* TODO These ids are too easy to mix up: this might be class_id, id, or internal_class_id
             *
             * TODO double check attendance links
             */
            $class['internal_class_id']
        );
    }
}
