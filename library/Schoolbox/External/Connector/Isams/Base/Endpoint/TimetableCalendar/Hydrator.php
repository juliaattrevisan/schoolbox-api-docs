<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

/**
 * Takes an unrolled Cycle and populates it with the classes,
 * humans, etc. which results in something far more manageable
 */
class Hydrator
{
    /**
     * @var \SimpleXMLElement
     */
    private \SimpleXMLElement $nodes;


    /**
     * The stack of weeks, days and periods that we must now fill
     * with classes and humans and such
     * @var array
     */
    private array $timetableDefinition;


    /**
     * Constructor.
     *
     * @param \SimpleXMLElement $nodes
     * @param array             $timetableDefinition
     */
    public function __construct(\SimpleXMLElement $nodes, array $timetableDefinition)
    {
        $this->nodes = $nodes;
        $this->timetableDefinition = $timetableDefinition;
    }


    public function hydrate(): void
    {
        $publishedTimetables = $this->nodes->xpath('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        foreach ($publishedTimetables as $publishedTimetable) {
            $schedules = $publishedTimetable->xpath('Schedules/Schedule');

            foreach ($schedules as $schedule) {
                $scheduleStruct = [
                    'isamsId' => (string)$schedule->xpath('@Id')[0],
                    'periodId' => (string)$schedule->PeriodId,
                    'code' => (string)$schedule->Code,
                    'staff' => $this->findTeacher((string)$schedule->Teacher),
                    'roomId' => (string)$schedule->RoomId,
                    'setId' => (string)$schedule->SetId,
                    'set' => (string)$schedule->Set
                ];

                new \Alaress_Probe($schedule, $scheduleStruct);
            }
        }
    }


    /**
     * Find a teacher's details via their isams UserCode
     *
     * @param string $userCode
     * @return string[]
     */
    public function findTeacher(string $userCode): array
    {
        $node = $this->nodes->xpath("/iSAMS/HRManager/CurrentStaff/StaffMember[UserCode[text()='{$userCode}']]")[0];

        return [
            'externalId' => (string)$node->xpath('@PersonId')[0],
            'title' => (string)$node->Title,
            'firstname' => (string)$node->Forename,
            'firstnamePreferred' => (string)$node->PreferredName,
            'lastname' => (string)$node->Surname,
        ];
    }
}
