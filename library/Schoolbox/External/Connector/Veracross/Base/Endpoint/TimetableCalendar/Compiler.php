<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

class Compiler
{
    /**
     * Constructor
     *
     * @param array $enrolmentsLookup
     * @param array $classesLookup
     * @param array $meetingTimesLookup
     */
    public function __construct(
        private array $enrolmentsLookup,
        private array $classesLookup,
        private array $meetingTimesLookup
    ) {
    }


    /**
     * Assembles a representation of a particular person's timetable that is easier to work with
     * than the raw data from veracross itself.
     *
     * @param string $externalId
     * @return array
     */
    public function compile(string $externalId): array
    {
        $timetable = [];


        return $timetable;
    }
}
