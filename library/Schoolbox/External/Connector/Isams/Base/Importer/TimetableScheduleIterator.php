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
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

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
        $this->schedules = $timetable->xpath('Schedules');
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
        $class = $this->schedules[$this->position];

        /* TODO: Find out class details by looking elsewhere in the xml
         */
        $name = '';
        $periodId = (string)$class->PeriodId;
        $roomId = (string)$class->RoomId;
        $teachers = [];
        $students = [];


        /* Basic TimetableClass data
         */
        $class = [
            'name' => $name,
            'code' => (string)$class->Code,
            'teachers' => $teachers,
            'students' => $students
        ];


//        $set = $this->sets[$this->position];
//
//        /* Find the subject's actual name by looking elsewhere in the xml
//         */
//        $subjectId = (string)$set->SubjectId;
//        $subject = $this->batchxml->fetchXml("/iSAMS/TeachingManager/Departments/Department/Subjects/Subject[@Id='{$subjectId}']")[0];
//
//        /* Basic TimetableClass data
//         */
//        $class = [
//            'name' => (string)$subject->Name,
//            'code' => (string)$set->SetCode,
//            'teachers' => $this->findTeachers($set),
//            'students' => $this->findStudents($set)
//        ];

        return $class;
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