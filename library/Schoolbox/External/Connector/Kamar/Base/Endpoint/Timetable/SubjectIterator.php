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
use UnexpectedValueException;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

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
    private $batchxml;


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
     * @param Batchxml         $batchxml
     * @param string           $studentId
     * @param SimpleXMLElement $day
     */
    public function __construct(Batchxml $batchxml, string $studentId, SimpleXMLElement $day)
    {
        $this->batchxml = $batchxml;
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
     *
     * @param SimpleXMLElement $subject
     * @return array
     */
    private function findTeacher(SimpleXMLElement $subject): array
    {

        /* TODO: Cross-reference with another xml document...
         */
        return [
            'title' => '',
            'firstname' => trim((string)$subject->teachers),
            'surname' => trim((string)$subject->teachers),
            'username' => trim((string)$subject->teachers),
        ];
    }


    /**
     * Find the students who were in subject $setCode
     *
     * @param string $studentId
     * @return array
     */
    private function findStudents(string $studentId): array
    {

        /* TODO: Cross-reference with another xml document...
         */
        $students[] = [
            'title' => '',
            'firstname' => $studentId,
            'surname' => $studentId,
            'username' => $studentId,
        ];

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
