<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

use Schoolbox\External\Connector\FirstMeaningfulValue;

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

        /* Assemble basic class info
         */
        $classes = $this->enrolmentsLookup[$externalId];

        foreach ($classes as $class) {
            $classId = $class['internal_class_id'];

            $timetable[$classId] = [
                'internal_class_id' => $classId,
                'name' => trim((string)$class['class_description']),
                /* Try not to get confused by all the class_id values floating around
                 * Some of them are a class code and some are primary keys in veracross
                 */
                'code' => $this->classesLookup[$classId]['class_id'] ?? null,
                'teachers' => [
                    [
                        'id' => $class['primary_teacher']['id'],
                        'name' => sprintf(
                            '%s %s %s',
                            $class['primary_teacher']['name_prefix'],
                            $class['primary_teacher']['last_name'],
                            (new FirstMeaningfulValue())->find([
                                $class['primary_teacher']['preferred_name'],
                                $class['primary_teacher']['first_name'],
                            ])
                        ),
                    ],
                ],
            ];
        }

        print_r($timetable);
        die();

        return $timetable;
    }
}
