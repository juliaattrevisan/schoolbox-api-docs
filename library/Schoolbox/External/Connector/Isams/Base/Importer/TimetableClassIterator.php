<?php
/**
 * TimetableClassIterator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Schoolbox\External\UPS\Batchxml;

class TimetableClassIterator implements \Iterator
{

    /**
     * @var int
     */
    private $position = 0;


    /**
     * @var array|\SimpleXMLElement[]
     */
    private $sets;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * TimetableClassIterator constructor.
     *
     * @param Batchxml $batchxml
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function __construct(Batchxml $batchxml)
    {
        $this->batchxml = $batchxml;

        $this->sets = $this->batchxml->fetchXml('/iSAMS/TeachingManager/Sets/Set');
    }


    /**
     * Return the current element
     *
     * Compile this into an array of convenient pieces
     *
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     * @since 5.0.0
     */
    public function current()
    {
        $set = $this->sets[$this->position];

        /* Class identifier
         */
        $setId = (string)$set->xpath('@Id')[0];

        /* Find the subject's actual name by looking elsewhere in the xml
         */
        $subjectId = (string)$set->SubjectId;
        $subject = $this->batchxml->fetchXml("/iSAMS/TeachingManager/Departments/Department/Subjects/Subject[@Id='{$subjectId}']")[0];

        /* A list of teachers for this class
         * Requires UPS to have loaded all Staff members
         */
        $teachers = [];

        /* Find the subject's teachers by looking elsewhere in the xml
         */
        foreach ($set->xpath('Teachers/Teacher') as $current) {
            $teacherId = (string)$current->xpath('@StaffId')[0]->StaffId;

            $teacher = $this->batchxml->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[@Id='{$teacherId}']")[0];

            $teachers[] = [
                'username' => (string)$teacher->UserName,
                'preferredName' => (string)$teacher->PreferredName,
                'surname' => (string)$teacher->Surname,
            ];
        }

        /* A list of students for this class
         * Requires UPS to have loaded all Student members
         */
        $students = [];

        /* Find the subject's students by looking elsewhere in the xml
         */
        $setListNodes = $this->batchxml->fetchXml("/iSAMS/TeachingManager/SetLists/SetList[@SetId='{$setId}']");

        foreach ($setListNodes as $current) {
            $studentId = (string)$current->xpath('@PupilId')[0];

            $student = $this->batchxml->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']")[0];

            $students[] = [
                'id' => (string)$student->SchoolCode,
                'username' => (string)$student->UserName,
                'preferredName' => (string)$student->Preferredname,
                'surname' => (string)$student->Surname
            ];
        }

        /* Basic TimetableClass data
         */
        $class = [
            'name' => (string)$subject->Name,
            'code' => (string)$set->SetCode,
            'teachers' => $teachers,
            'students' => $students
        ];

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
        return isset($this->sets[$this->position]);
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