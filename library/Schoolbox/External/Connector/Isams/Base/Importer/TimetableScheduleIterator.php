<?php
/**
 * TiimetableScheduleIterator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Iterator;
use RuntimeException;
use Schoolbox\External\UPS\Batchxml;
use SimpleXMLElement;

class TimetableScheduleIterator implements Iterator
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

        /* TODO: Find out class details by looking elsewhere in the xml
         */
//        $periodId = (string)$class->PeriodId;
//        $roomId = (string)$class->RoomId;

        /* Find the subject's actual name by looking elsewhere in the xml
         */
        $setCode = (string)$schedule->Code;
        $setName = $this->findSubjectName($setCode);

        /* Teacher
         * (I've only seen one per class in the xml so far...but there could be many?)
         */
        $teachers = [$this->findTeacher((string)$schedule->Teacher)];

        /* Students
         */
        $students = $this->findStudents($setCode);

        /* Basic TimetableClass data
         */
        $class = [
            'code' => $setCode,
            'name' => $setName,
            'teachers' => $teachers,
            'students' => $students
        ];

//        print_r($schedule);
        print_r($class);

        return $class;
    }


    /**
     * Find a subject's name from its $setCode (that's its subject code equivalent)
     *
     *
     * @param string $setCode
     * @return string
     * @throws RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findSubjectName(string $setCode): string
    {
        $node = $this->batchxml->fetchXml("/iSAMS/TeachingManager/Sets/Set[contains(SetCode, '{$setCode}')]");

        /* Unknown subject: can't find it via its setCode
         */
        if (empty($node)) {
            return '(unknown subject)';
        }

        return (string)$node[0]->Name;
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
            'title' => (string)$teacher->Title,
            'firstname' => (string)$teacher->Forename,
            'surname' => (string)$teacher->Surname,
            'username' => (string)$teacher->xpath('@PersonId')[0]->PersonId,
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

        $setId = (string)$node[0]->xpath('@Id')[0]->Id;

        $setListNodes = $this->batchxml->fetchXml("/iSAMS/TeachingManager/SetLists/SetList[contains(SetId, '{$setId}')]");

        foreach ($setListNodes as $current) {
            $studentId = (string)$current->xpath('@PupilId')[0];

            $student = $this->batchxml->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']")[0];

            /* Unknown student; can't find it via its studentId
             */
            if (empty($student)) {
                continue;
            }

            $students[] = [
                'title' => (string)$student->Title,
                'firstname' => (string)$student->Forename,
                'surname' => (string)$student->Surname,
                'username' => (string)$student->SchoolCode,
            ];
        }

        return $students;
    }


    /**
     * Move forward to next element
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