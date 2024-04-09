<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\TimetableCalendar\Metadata;

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
     * @param array $classInstance
     * @return array
     */
    public function build(array $classInstance): array
    {
        /* Each event contains this additional metadata struct,
         * for extra info that you might need later
         */
        return [
            'links' => [
                'attendance' => $classInstance['attributes']['rollMarkingUrl'] ?? null
            ]
        ];
    }
}
