<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable;

use Iterator;
use RuntimeException;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use SimpleXMLElement;
use Laminas\Uri\Exception\InvalidArgumentException;
use Laminas\Uri\Exception\InvalidUriPartException;

/**
 * Class ScheduleIterator
 *
 * Iterate a series of Schedule elements (these correspond to when periods occur)
 * and collate all the people and details involved
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable
 */
class ScheduleIterator implements Iterator
{
    /**
     * @var int
     */
    private int $position = 0;


    /**
     * @var SimpleXMLElement
     */
    private SimpleXMLElement $schedules;


    /**
     * @var array
     */
    private array $api;


    /**
     * @var DayCalibrator
     */
    private DayCalibrator $dayCalibrator;


    /**
     * TimetableClassIterator constructor.
     *
     * @param array            $api
     * @param SimpleXMLElement $timetable
     * @throws RuntimeException
     */
    public function __construct(array $api, SimpleXMLElement $timetable)
    {
        $this->api = $api;

        /* Published timetables might, or might not, have Schedules nodes
         * because classes haven't been assigned to a timetable yet
         */
        if (!isset($timetable->xpath('Schedules')[0])) {
            throw new \RuntimeException('No schedules found in this timetable; nothing to process.');
        }

        $this->schedules = $timetable->xpath('Schedules')[0]->Schedule;
        $this->dayCalibrator = new DayCalibrator($api);
    }


    /**
     * Return the current element
     *
     * Compile this into an array of convenient pieces
     *
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     * @since 5.0.0
     */
    public function current()
    {
        $schedule = $this->schedules[$this->position];

        /* Class
         */
        $subject = $this->findSubject(trim((string)$schedule->Code));

        /* Teacher
         * (I've only seen one per class in the xml so far...but there could be many?)
         */
        $teachers = $this->findTeacher(trim((string)$schedule->Teacher));

        /* Students
         */
        $students = $this->findStudents(trim((string)$schedule->Code));

        /* Find out when this happens in the grand design of the timetable
         */
        $period = $this->findPeriod($schedule);

        /* Basic TimetableClass data
         */
        $class = [
            'class' => $subject,
            'period' => $period,
            'teachers' => $teachers,
            'students' => $students
        ];

        return $class;
    }


    /**
     * Find a subject's name from its $setCode (that's its subject code equivalent)
     *
     * @param string $setCode
     * @return array
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    private function findSubject(string $setCode): array
    {
        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/Sets/Set[contains(SetCode, '{$setCode}')]");

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            return [
                'code' => '',
                'name' => '(unknown subject)',
            ];
        }

        return [
            'code' => $setCode,
            'name' => trim((string)$node[0]->Name),
        ];
    }


    /**
     * Find period data for a given $periodId
     *
     * @param SimpleXMLElement $schedule
     * @return array
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    private function findPeriod(SimpleXMLElement $schedule): array
    {
        $periodId = trim((string)$schedule->PeriodId);

        $periodPath = "/iSAMS/TimetableManager/Structure/Week/Days/Day/Periods/Period[@Id='{$periodId}']";

        $period = $this->api[DSN::MODULE_BATCHXML]->fetchXml($periodPath);

        /* Unknown period
         */
        if (empty($period)) {
            return [
                'day' => '',
                'sequence' => '',
                'name' => '(unknown period)',
                'abbrev' => '',
                'start' => '',
                'end' => '',
                'room' => []
            ];
        }

        /* Find out what day this was in
         */
        $dayPath = "{$periodPath}/ancestor::*[position()=2]";

        $day = $this->api[DSN::MODULE_BATCHXML]->fetchXml($dayPath);

        /* Unknown day
         */
        if (empty($day)) {
            return [
                'day' => '(unknown day)',
                'sequence' => '',
                'name' => '',
                'abbrev' => '',
                'start' => '',
                'end' => '',
                'room' => []
            ];
        }

        /* Otherwise we knew when this happened and what was in it
         */
        return [
            'day' => $this->dayCalibrator->determineSchoolboxDay((int)$day[0]->Day),
            'name' => trim((string)$period[0]->Name),
            'abbrev' => trim((string)$period[0]->ShortName),
            'start' => trim((string)$period[0]->StartTime),
            'end' => trim((string)$period[0]->EndTime),
            'sequence' => trim((string)$period[0]->Ordinal),
            'room' => $this->findRoom(trim((string)$schedule->RoomId))
        ];
    }


    /**
     * Find room data for a given $roomId
     *
     * @param string $roomId
     * @return array
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    private function findRoom(string $roomId): array
    {
        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/EstateManager/Buildings/Building/Classrooms/Classroom[@Id='{$roomId}']");

        if (empty($node)) {
            return [
                'name' => '(unknown room)',
                'initials' => '',
                'code' => '',
            ];
        }

        return [
            'name' => trim((string)$node[0]->Name),
            'initials' => trim((string)$node[0]->Initials),
            'code' => trim((string)$node[0]->Code),
        ];
    }


    /**
     * Find a teacher's details from its $teacherCode
     *
     * @param string $teacherCode
     * @return array
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    private function findTeacher(string $teacherCode): array
    {
        $defaultTeacher = [
            'title' => '',
            'firstname' => '(unknown teacher)',
            'surname' => '',
            'username' => '',
        ];

        /* $teacherCode is empty; there wasn't a specified Teacher
         * in the schedule; return Unknown Teacher as default case
         */
        if ('' === $teacherCode) {
            return $defaultTeacher;
        }

        /* Otherwise, go hunting for them
         */
        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[UserCode[text()='{$teacherCode}']]");

        /* Unknown teacher: can't find it via its setCode
         */
        if (empty($node)) {
            return $defaultTeacher;
        }

        $teacher = $node[0];

        return [
            'title' => trim((string)$teacher->Title),
            'firstname' => trim((string)$teacher->Forename),
            'surname' => trim((string)$teacher->Surname),
            'username' => trim((string)$teacher->xpath('@PersonId')[0]->PersonId),
        ];
    }


    /**
     * Find the students who were in subject $setCode
     *
     * @param string $setCode
     * @return array
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    private function findStudents(string $setCode): array
    {
        $node = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/Sets/Set[contains(SetCode, '{$setCode}')]");

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            return [];
        }

        /* Otherwise continue digging
         */
        $students = [];

        $setId = trim((string)$node[0]->xpath('@Id')[0]->Id);

        /* Select all the SetList nodes where any of its SetId subnodes contains the value $setId exactly
         */
        $setListNodes = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TeachingManager/SetLists/SetList[SetId[text()='{$setId}']]");

        foreach ($setListNodes as $current) {
            $studentId = trim((string)$current->xpath('@PupilId')[0]);

            $student = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']")[0];

            /* Unknown student; can't find it via its studentId
             */
            if (empty($student)) {
                continue;
            }

            $students[] = [
                'externalId' => trim((string)$student->SchoolId),
                'title' => trim((string)$student->Title),
                'firstname' => trim((string)$student->Forename),
                'surname' => trim((string)$student->Surname),
                'username' => trim((string)$student->SchoolCode),
            ];
        }

        return $students;
    }


    /**
     * Move forward to next element
     *
     * @link  https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next(): void
    {
        ++$this->position;
    }


    /**
     * Return the key of the current element
     * @link  https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->position;
    }


    /**
     * Checks if current position is valid
     * @link  https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid(): bool
    {
        return isset($this->schedules[$this->position]);
    }


    /**
     * Rewind the Iterator to the first element
     * @link  https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind(): void
    {
        $this->position = 0;
    }
}
