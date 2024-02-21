<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar;

class Metadata
{
    /**
     * Constructor
     *
     * @param array $class
     * @param array $classInstance
     */
    public function __construct(private array $class, private array $classInstance)
    {
    }


    /**
     * Builds event-specific metadata such as attendance links etc.
     *
     * @return array
     */
    public function build(): array
    {
        return [
            'links' => [
                'attendance' => $this->buildAttendanceLink()
            ]
        ];
    }


    /**
     * Precompute a deeplinking url into Civica using the ingredients provided
     *
     * @return null|string
     */
    private function buildAttendanceLink(): ?string
    {
        $attendanceLink = null;

        return $attendanceLink;
    }
}
