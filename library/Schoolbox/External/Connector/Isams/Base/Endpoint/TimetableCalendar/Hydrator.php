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
                $classCode = (string)$schedule->Code;
                $subject = $this->findSubject($classCode);
                $scheduleId = (string)$schedule->xpath('@Id')[0];

                $stack[$periodId]['schedule'][$scheduleId] = [
                    'subject' => $subject,
                    'substituteStaff' => $this->findScheduleStaff((string)$schedule->Teacher),
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
     * I *think* this is for Substitute Teachers, given that the school had
     * this person for a Schedule:
     *
     * Array
        (
            [externalId] => 50638
            [staffId] => 508
            [userCode] => cvr119765041396889606
            [title] => Miss
            [firstname] => Secondary
            [firstnamePreferred] => Secondary
            [lastname] => Cover 1
        )
     *
     * The Subject's Teacher is sometimes empty, so I guess this means it's
     * always covered by a subby. Or, they're not specified. Or, it's actually
     * the other way around. I've seen Secondary Cover in both the regular
     * subject staff and this schedule staff.
     *
     * @param string $userCode
     * @return string[]
     */
    public function findScheduleStaff(string $userCode): array
    {
        $node = $this->nodes->xpath("/iSAMS/HRManager/CurrentStaff/StaffMember[UserCode[text()='{$userCode}']]")[0] ?? null;

        /* Couldn't find this teacher
         */
        if (null === $node) {
            return [
                'externalId' => '',
                'staffId' => '',
                'userCode' => $userCode,
                'title' => '',
                'firstname' => '',
                'firstnamePreferred' => '',
                'lastname' => '',
            ];
        }

        return [
            'externalId' => (string)$node->xpath('@PersonId')[0],
            'staffId' => (string)$node->xpath('@Id')[0],
            'userCode' => $userCode,
            'title' => (string)$node->Title,
            'firstname' => (string)$node->Forename,
            'firstnamePreferred' => (string)$node->PreferredName,
            'lastname' => (string)$node->Surname,
        ];
    }


    /**
     * Find a staff member's info via internal isams @Id
     *
     * @param string $id
     * @return array
     */
    public function findSubjectStaff(string $id): array
    {
        $node = $this->nodes->xpath("/iSAMS/HRManager/CurrentStaff/StaffMember[@Id='{$id}']")[0] ?? null;

        /* Couldn't find this teacher
         */
        if (null === $node) {
            return [
                'externalId' => '',
                'staffId' => $id,
                'userCode' => '',
                'title' => '',
                'firstname' => '',
                'firstnamePreferred' => '',
                'lastname' => '',
            ];
        }

        return [
            'externalId' => (string)$node->xpath('@PersonId')[0],
            'staffId' => $id,
            'userCode' => (string)$node->UserCode,
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

        /* TODO There is a <Teachers> node here, but there's always been
         *  exactly one <Teacher> node contained inside. If multiple teachers
         *  is possible, this is where you'd find them
         *
         * TODO It might be possible this is used for Substitute Teachers
         *  and that Schedule->Teacher is the Regular Guy
         */

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            return [
                'subjectId' => '',
                'code' => '',
                'name' => '(unknown subject)',
                'regularStaff' => []
            ];
        }

        /* Collect whatever these nodes mean; not sure yet
         */
        $staff = [];

        $teachers = $node->xpath('Teachers/Teacher');

        /* lol the Teacher @Id attribute is the Set Id, not the teacher's...
         */
        foreach ($teachers as $teacher) {
            $staff[] = $this->findSubjectStaff((string)$teacher->xpath('@StaffId')[0]);
        }

        return [
            'subjectId' => (string)$node->SubjectId,
            'code' => $setCode,
            'name' => trim((string)$node[0]->Name),
            'regularStaff' => $staff
        ];
    }
}
