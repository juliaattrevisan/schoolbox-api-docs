<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use InvalidArgumentException;
use Iterator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile\Container;
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
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var Container
     */
    private $container;


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
     * @var int
     */
    private $position = 0;


    /**
     * SubjectIterator constructor.
     *
     * @param LoggerInterface  $log
     * @param Container        $container
     * @param string           $studentId
     * @param SimpleXMLElement $day
     */
    public function __construct(LoggerInterface $log, Container $container, string $studentId, SimpleXMLElement $day)
    {
        $this->log = $log;
        $this->container = $container;
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
     * @throws InvalidArgumentException
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
            'student' => $students
        ];

        $this->log->info(sprintf(
            'Processing %s/%s/%s/%s with %s teachers',
            $this->day,
            $period,
            $subject,
            $room,
            \count($teachers)
        ));

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

        /* Figure out a subject code
         */
        if (isset($subjectNode->code)) {
            $code = trim((string)$subjectNode->code);
        }

        /* Figure out the subject's name
         * There are some special constructs for optional classes
         * Take a punt as to what they're called...
         */
        if (isset($subjectNode->core)) {
            $name = trim((string)$subjectNode->core);
        } elseif (isset($subjectNode->option)) {
            $name = implode('-', array_filter([
                trim((string)$subjectNode->option),
                $code,
                trim((string)$subjectNode->teachers)
            ]));
        } else {

            /* No subjects in this slot
             */
            return null;
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
     */
    private function findTeacher(SimpleXMLElement $subject): array
    {

        /* Accumulate all the teachers here
         * Assign one to be the authoritative teacher
         * Add the others as class attendees
         */
        $teachers = [];

        /* For some reason Kamar is comma-separating multiple teachers per class
         * Why wouldn't they just give me multiple <teacher> nodes instead?
         * That's the point of using xml for this sort of stuff...?
         */
        $teacherIds = explode(',', trim((string)$subject->teachers));

        foreach ($teacherIds as $teacherId) {
            $teacher = $this->container->recall($teacherId);

            if (!$teacher) {
                $this->log->warning(sprintf(
                    'Teacher %s profile not found',
                    $teacherId
                ));

                continue;
            }

            $teachers[] = [
                'externalId' => $teacher['externalId'],
                'title' => $teacher['title'],
                'firstname' => $teacher['firstname'],
                'surname' => $teacher['lastname'],
                'username' => $teacher['username'],
            ];
        }

        return $teachers;
    }


    /**
     * Find the students who were in subject $setCode
     * Cross-reference with another xml document to find the student's name
     *
     * @param string $studentId
     * @return array|null
     */
    private function findStudents(string $studentId): ?array
    {
        $student = $this->container->recall($studentId);

        if (!$student) {
            $this->log->warning(sprintf(
                'Student %s profile not found',
                $studentId
            ));

            return null;
        }

        return [
            'externalId' => $student['externalId'],
            'title' => $student['title'],
            'firstname' => $student['firstname'],
            'surname' => $student['lastname'],
            'username' => $student['username'],
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
