<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer\Timetable;

use Iterator;
use RuntimeException;
use Schoolbox\External\Connector\Isams\Base\Importer\Batchxml;
use SimpleXMLElement;

/**
 * Class ScheduleIterator
 *
 * Iterate a series of Schedule elements (these correspond to when periods occur)
 * and collate all the people and details involved
 *
 * @package Schoolbox\External\Connector\Isams\Base\Importer\Timetable
 */
class ScheduleIterator implements Iterator
{
    /**
     * @var int
     */
    private $position = 0;


    /**
     * @var array|SimpleXMLElement[]
     */
    private $schedules;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * TimetableClassIterator constructor.
     *
     * @param Batchxml         $batchxml
     * @param SimpleXMLElement $timetable
     */
    public function __construct(Batchxml $batchxml, SimpleXMLElement $timetable)
    {
        $this->batchxml = $batchxml;
        $this->schedules = $timetable->xpath('Schedules')[0]->Schedule;
    }


    /**
     * Return the current element
     *
     * Compile this into an array of convenient pieces
     *
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @throws RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
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
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findSubject(string $setCode): array
    {
        $node = $this->batchxml->fetchXml("/iSAMS/TeachingManager/Sets/Set[contains(SetCode, '{$setCode}')]");

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
     * @return array|SimpleXMLElement[]|string
     * @throws RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findPeriod(SimpleXMLElement $schedule)
    {
        $periodId = trim((string)$schedule->PeriodId);

        $periodPath = "/iSAMS/TimetableManager/Structure/Week/Days/Day/Periods/Period[@Id='{$periodId}']";

        $period = $this->batchxml->fetchXml($periodPath);

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

        $day = $this->batchxml->fetchXml($dayPath);

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
            'day' => trim((string)$day[0]->Day),
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
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findRoom(string $roomId): array
    {
        $node = $this->batchxml->fetchXml("/iSAMS/EstateManager/Buildings/Building/Classrooms/Classroom[@Id='{$roomId}']");

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
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findTeacher(string $teacherCode): array
    {
        $node = $this->batchxml->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[contains(UserCode, '{$teacherCode}')]");

        /* Unknown teacher: can't find it via its setCode
         */
        if (empty($node)) {
            return [
                'title' => '',
                'firstname' => '(unknown teacher)',
                'surname' => '',
                'username' => '',
            ];
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
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findStudents(string $setCode): array
    {
        $node = $this->batchxml->fetchXml("/iSAMS/TeachingManager/Sets/Set[contains(SetCode, '{$setCode}')]");

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            return [];
        }

        /* Otherwise continue digging
         */
        $students = [];

        $setId = trim((string)$node[0]->xpath('@Id')[0]->Id);

        $setListNodes = $this->batchxml->fetchXml("/iSAMS/TeachingManager/SetLists/SetList[contains(SetId, '{$setId}')]");

        foreach ($setListNodes as $current) {
            $studentId = trim((string)$current->xpath('@PupilId')[0]);

            $student = $this->batchxml->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']")[0];

            /* Unknown student; can't find it via its studentId
             */
            if (empty($student)) {
                continue;
            }

            $students[] = [
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
