<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Iterator;
use RuntimeException;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use SimpleXMLElement;

/**
 * Class SubjectIterator
 *
 * Iterate a series of Subject elements (these correspond to when periods occur)
 * and collate all the people and details involved
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class SubjectIterator implements Iterator
{
    /**
     * @var int
     */
    private $position = 0;


    /**
     * @var Batchxml
     */
    private $profileBatchXml;


    /**
     * @var Batchxml
     */
    private $timetableBatchXml;


    /**
     * @var array|SimpleXMLElement[]
     */
    private $subjects;


    /**
     * @var string
     */
    private $studentId;


    /**
     * @var string
     */
    private $day;


    /**
     * SubjectIterator constructor.
     *
     * @param Batchxml         $profileBatchXml
     * @param string           $studentId
     * @param SimpleXMLElement $day
     */
    public function __construct(Batchxml $profileBatchXml, string $studentId, SimpleXMLElement $day)
    {
        $this->profileBatchXml = $profileBatchXml;
        $this->studentId = $studentId;
        $this->subjects = $day->xpath('slots/subjects');
        $this->day = trim((string)$day->xpath('@index')[0]);
    }


    /**
     * Return the current element
     *
     * Compile this into an array of convenient pieces
     *
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     * @throws RuntimeException
     */
    public function current()
    {
        $subjectNode = $this->subjects[$this->position];

        /* Skip empty slots that don't have any subject in them
         */
        if (null === $subjectNode->subject->code) {
            return null;
        }

        /* Class
         */
        $subject = $this->findSubject($subjectNode->subject);

        /* Teacher
         * (I've only seen one per class in the xml so far...but there could be many?)
         */
        $teachers = $this->findTeacher($subjectNode->subject);

        /* Students
         */
        $students = $this->findStudents($this->studentId);

        /* Room
         */
        $room = $this->findRoom($subjectNode->subject);

        /* Period Name
         * TODO: Unify with usage in Definition.php
         */
        $period = 'Period ' . (int)$subjectNode->xpath('@slot')[0];

        /* Basic TimetableClass data
         */
        $class = [
            'day' => $this->day,
            'period' => $period,
            'class' => $subject,
            'room' => $room,
            'teachers' => $teachers,
            'students' => $students
        ];

        return $class;
    }


    /**
     * Find a subject's name
     * It can be either in the Core node or the Option node
     *
     * @param SimpleXMLElement $subjectNode
     * @return array|null
     */
    private function findSubject(SimpleXMLElement $subjectNode): ?array
    {
        $code = null;

        if (isset($subjectNode->core)) {
            $name = trim((string)$subjectNode->core);
        } elseif (isset($subjectNode->option)) {
            $name = trim((string)$subjectNode->option);
        } else {

            /* No subjects in this slot
             */
            return null;
        }

        if (isset($subjectNode->code)) {
            $code = trim((string)$subjectNode->code);
        }

        return [
            'code' => $code,
            'name' => $name,
        ];
    }


    /**
     * Find room data for a given $roomId
     *
     * @param SimpleXMLElement $subjectNode
     * @return array
     */
    private function findRoom(SimpleXMLElement $subjectNode): array
    {
        return [
            'name' => trim((string)$subjectNode->rooms)
        ];
    }


    /**
     * Find a teacher's details
     * Cross-reference with another xml document to find the teacher's name
     *
     * @param SimpleXMLElement $subject
     * @return array
     * @throws RuntimeException
     */
    private function findTeacher(SimpleXMLElement $subject): array
    {
        $teacherId = trim((string)$subject->teachers);

        $teacherNode = $this->profileBatchXml->fetchXml("/SMSDirectoryData/staff/user[@id='{$teacherId}']");

        if (!$teacherNode) {
            throw new RuntimeException("Teacher {$teacherId} profile not found");
        }

        $teacher = $teacherNode[0];

        return [
            'externalId' => trim((string)$teacher->uniqueid),
            'title' => trim((string)$teacher->title),
            'firstname' => trim((string)$teacher->firstname),
            'surname' => trim((string)$teacher->lastname),
            'username' => trim((string)$teacher->username),
        ];
    }


    /**
     * Find the students who were in subject $setCode
     * Cross-reference with another xml document to find the student's name
     *
     * @param string $studentId
     * @return array
     * @throws RuntimeException
     */
    private function findStudents(string $studentId): array
    {
        $studentNode = $this->profileBatchXml->fetchXml("/SMSDirectoryData/students/user[@id='{$studentId}']");

        if (!$studentNode) {
            throw new RuntimeException("Student {$studentId} profile not found");
        }

        $student = $studentNode[0];

        return [
            'externalId' => trim((string)$student->uniqueid),
            'title' => '',
            'firstname' => trim((string)$student->firstname),
            'surname' => trim((string)$student->lastname),
            'username' => trim((string)$student->username),
        ];
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
        return isset($this->subjects[$this->position]);
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
