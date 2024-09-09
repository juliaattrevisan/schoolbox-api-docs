<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Concattery;

/**
 * Finds all the schedules that are relevant to the given $timetableDefinition
 * by collecting all the PeriodIds contained therein
 */
class ScheduleCompiler
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param array $api
     * @param array $timetableDefinition  The stack of weeks, days and periods that we must now fill with classes and humans and such
     */
    public function __construct(
        private LoggerInterface $log,
        private array $api,
        private array $timetableDefinition
    ) {
    }


    /**
     * Find all the Published Schedules, which are schedules that exist inside
     * a Published Timetable, but we only care about schedules that could populate
     * the known $periodIds in this definition
     *
     * @return array
     */
    public function findSchedules(): array
    {
        $periodIds = $this->findPeriodIds();

        $publishedTimetables = $this->api[DSN::MODULE_BATCHXML]->fetchXml('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        $stack = [];

        foreach ($publishedTimetables as $publishedTimetable) {
            $schedules = $publishedTimetable->xpath('Schedules/Schedule');

            foreach ($schedules as $schedule) {
                $periodId = (string)$schedule->PeriodId;

                /* This periodId isn't in the definition; skip it
                 */
                if (!isset($periodIds[$periodId])) {
                    continue;
                }

                $classCode = (string)$schedule->Code;
                $scheduleId = (string)$schedule->xpath('@Id')[0];

                /* Obtain this separately as you iterate rather than precompile
                 * everything up-front
                 *
                 * $this->findSubject($classCode);
                 */
                $subject = null;

                $stack[$periodId]['schedule'][$scheduleId] = [
                    'code' => $classCode,
                    'subject' => $subject,
                    'substituteStaff' => $this->findScheduleStaff((string)$schedule->Teacher),
                    'room' => $this->findRoom((string)$schedule->RoomId),
                    'setId' => (string)$schedule->SetId,
                    'set' => (string)$schedule->Set,
                ];
            }
        }

        return $stack;
    }


    /**
     * Find a subject's name and humans from its $setCode (that's its subject code equivalent)
     *
     * @param string $setCode
     * @param string $divisionId
     * @return array
     */
    public function findSubject(string $setCode, string $divisionId): array
    {
        /* $setCode might contain single- or double-quotes
         * Handle with special treatment
         */
        $searchTerm = (new Concattery())->generate($setCode);

        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/Sets/Set[SetCode[text()={$searchTerm}]]")[0] ?? null;
        //null coalesce fetchXml()[0] as that field might not exist... but don't throw warnings either

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
            $this->log->debug(sprintf(
                'SetCode "%s" does not have any Sets in it; searched via: %s',
                $setCode,
                $searchTerm
            ));

            return [
                'subjectId' => '',
                'code' => '',
                'name' => '(unknown subject)',
                'regularStaff' => [],
                'students' => [],
            ];
        }

        /* Collect whatever these nodes mean; not sure yet
         */
        $staff = [];

        $teachers = $node->xpath('Teachers/Teacher');

        /* No teachers? No trabajo, but log it and keep going
         */
        if (!\count($teachers)) {
            $this->log->debug(sprintf(
                'SetCode "%s" as no teachers in it',
                $setCode
            ));
        }

        /* lol the Teacher @Id attribute is the Set Id, not the teacher's...
         */
        foreach ($teachers as $teacher) {
            $staff[] = $this->findSubjectStaff((string)$teacher->xpath('@StaffId')[0]);
        }

        /* Subject name is found in this random place, Departments > Subjects
         * But start with a default in case Departments and Subjects was not turned on
         */
        $subjectName = trim((string)$node[0]->Name);

        /* Try to find a nice human-readable version of the $subjectName
         * (at this point it's actually the set code, despite what the xml says)
         */
        $subjectId = trim((string)$node->xpath('@SubjectId')[0]->SubjectId);

        /* This node won't exist unless "Departments and Subjects" is turned on in schools' iSAMS
         * (it's some kind of isams configuration setting, and thus, optional)
         *
         * Anyway, it's where you find a nice human-readable version of a subject's class code ($setCode)
         */
        $subject = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/Departments/Department/Subjects/Subject[@Id='{$subjectId}']")[0] ?? null;

        if ($subject && '' !== trim((string)$subject->Name)) {
            $subjectName = trim((string)$subject->Name);
        }

        return [
            'subjectId' => (string)$node->SubjectId,
            'code' => $setCode,
            'name' => $subjectName,
            'regularStaff' => $staff,
            'students' => $this->findSubjectStudents($setCode, $divisionId)
        ];
    }


    /**
     * Find periodIds that we actually care about; anything not mentioned
     * in the timetableDefinition has no relevance
     *
     * @return array
     */
    private function findPeriodIds(): array
    {
        $periodIds = [];

        foreach ($this->timetableDefinition as $divisionId => $division) {
            foreach ($division as $weekId => $weekData) {
                foreach ($weekData['days'] as $day) {
                    foreach ($day['periods'] as $periodId => $periodData) {
                        $periodIds[$periodId] = $periodId;
                    }
                }
            }
        }

        return $periodIds;
    }


    /**
     * Find a room by its isams $roomId
     *
     * @param string $roomId
     * @return string[]
     */
    private function findRoom(string $roomId): array
    {
        $room = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/EstateManager/Buildings/Building/Classrooms/Classroom[@Id='{$roomId}']")[0] ?? null;

        /* 404 room not found.
         */
        if (null === $room) {
            $this->log->debug(sprintf(
                'Classroom with id "%s" was not found',
                $roomId
            ));

            return [
                'roomId' => $roomId,
                'name' => '',
                'code' => ''
            ];
        }

        return [
            'roomId' => $roomId,
            'name' => (string)$room->Name,
            'code' => (string)$room->Code,
        ];
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
    private function findScheduleStaff(string $userCode): array
    {
        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[UserCode[text()='{$userCode}']]")[0] ?? null;

        /* Couldn't find this teacher
         */
        if (null === $node) {
            $this->log->debug(sprintf(
                'StaffMember with UserCode "%s" was not found',
                $userCode
            ));

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
    private function findSubjectStaff(string $id): array
    {
        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[@Id='{$id}']")[0] ?? null;

        /* Couldn't find this teacher
         */
        if (null === $node) {
            $this->log->debug(sprintf(
                'StaffMember with id %s was not found',
                $id
            ));

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
     * Find the students who were in subject $setCode
     *
     * @param string $setCode
     * @param string $divisionId
     * @return array
     */
    private function findSubjectStudents(string $setCode, string $divisionId): array
    {
        /* $setCode might contain single- or double-quotes
         * Handle with special treatment
         */
        $searchTerm = (new Concattery())->generate($setCode);

        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/Sets/Set[SetCode[text()={$searchTerm}]]");

        $students = [];

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            $this->log->debug(sprintf(
                'SetCode %s as no students in it; searched via: %s',
                $setCode,
                $searchTerm
            ));

            return $students;
        }

        /* Otherwise continue digging
         */
        $setId = trim((string)$node[0]->xpath('@Id')[0]->Id);

        /* Select all the SetList nodes where any of its SetId subnodes contains the value $setId exactly
         */
        $setListNodes = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/SetLists/SetList[SetId[text()='{$setId}']]");

        /* The Set ("class") doesn't contain any SetList ("people in the class") nodes
         * Exit early
         */
        if (!\count($setListNodes)) {
            $this->log->debug(sprintf(
                'Did not find any SetList nodes for Set %s (SetCode/ClassCode "%s") - no students found.',
                $setId,
                $setCode
            ));

            return $students;
        }

        /* Find out who each guy really is
         */
        foreach ($setListNodes as $current) {
            $studentId = trim((string)$current->xpath('@PupilId')[0]);

            /* See if student with PupilId $studentId is also in DivisionId $divisionId
             * This tuple must match, to prevent students cross-pollinating into other
             * Division's timetables
             */
            $this->log->debug(sprintf(
                'Looking for %s in division %s',
                $studentId,
                $divisionId
            ));

            $student = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}' and @DivisionId='{$divisionId}']")[0] ?? null;

            /* Unknown student; can't find it via its (studentId, divisionId) tuple
             * Either the student doesn't exist, or it exists for a different Division
             * than the one we're currently processing. Timetable Calendar stuff allows
             * a Division to overlap another Division, resulting in duplicate classes.
             */
            if (empty($student)) {
                $this->log->debug(sprintf(
                    'Student with PupilId %s was not found in division %s',
                    $studentId,
                    $divisionId
                ));

                continue;
            }

            $students[] = [
                'externalId' => trim((string)$student->SchoolId),
                'studentId' => trim((string)$student->xpath('@Id')[0]),
                'userCode' => trim((string)$student->UserCode),
                'username' => trim((string)$student->SchoolCode),
                'title' => trim((string)$student->Title),
                'firstname' => trim((string)$student->Forename),
                'firstnamePreferred' => trim((string)$student->Preferredname),
                'lastname' => trim((string)$student->Surname),
            ];
        }

        return $students;
    }
}
