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


    /**
     * Manufacture a hydrated definition
     *
     * @return array
     */
    public function hydrate(): array
    {
        $publishedTimetables = $this->nodes->xpath('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        $stack = [];
        
        foreach ($publishedTimetables as $publishedTimetable) {
            $schedules = $publishedTimetable->xpath('Schedules/Schedule');

            foreach ($schedules as $schedule) {
                $periodId = (string)$schedule->PeriodId;
                
                $stack[$periodId][] = [
                    'isamsId' => (string)$schedule->xpath('@Id')[0],
                    'periodId' => $periodId,
                    'code' => (string)$schedule->Code,
                    'staff' => $this->findTeacher((string)$schedule->Teacher),
                    'roomId' => (string)$schedule->RoomId,
                    'setId' => (string)$schedule->SetId,
                    'set' => (string)$schedule->Set,
                ];
            }
        }

        return $stack;
    }


    /**
     * Find a teacher's details via their isams UserCode
     *
     * @param string $userCode
     * @return string[]
     */
    public function findTeacher(string $userCode): array
    {
        $node = $this->nodes->xpath("/iSAMS/HRManager/CurrentStaff/StaffMember[UserCode[text()='{$userCode}']]")[0] ?? null;

        /* Couldn't find this teacher
         */
        if (null === $node) {
            return [
                'externalId' => '',
                'userCode' => $userCode,
                'title' => '',
                'firstname' => '',
                'firstnamePreferred' => '',
                'lastname' => '',
            ];
        }

        return [
            'externalId' => (string)$node->xpath('@PersonId')[0],
            'userCode' => $userCode,
            'title' => (string)$node->Title,
            'firstname' => (string)$node->Forename,
            'firstnamePreferred' => (string)$node->PreferredName,
            'lastname' => (string)$node->Surname,
        ];
    }


    /**
     * Find a subject's name from its $setCode (that's its subject code equivalent)
     *
     * @param string $setCode
     * @return array
     */
    private function findSubject(string $setCode): array
    {
        $node = $this->nodes->xpath("/iSAMS/TeachingManager/Sets/Set[SetCode[text()='{$setCode}']]")[0];

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            return [
                'subjectId' => '',
                'code' => '',
                'name' => '(unknown subject)',
            ];
        }

        return [
            'subjectId' => (string)$node->SubjectId,
            'code' => $setCode,
            'name' => trim((string)$node[0]->Name),
        ];
    }
}
