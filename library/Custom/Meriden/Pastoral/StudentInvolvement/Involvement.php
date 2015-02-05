<?php
/**
 * Representation of a subject within Meriden.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\User;
use Schoolbox\Resource\Category;

class Involvement
{
    /**
     * The year that this tutor group is in
     *
     * @var int
     */
    protected $year;

    /**
     * The student that this tutor group is retrieved for
     *
     * @var User
     */
    protected $student;

    /**
     * The Schoolbox subject code for this tutor group
     *`
     * @var string
     */
    protected $subjectCode;

    /**
     * The name for this involvement
     *
     * @var string
     */
    protected $name;

    /**
     * The student's grades in this group
     *
     * @var array
     */
    protected $grades;

    /**
     * The Schoolbox folder for this tutor group for this student
     *
     * @var Category
     */
    protected $folder;

    /**
     * @return int
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * @param  int $year
     *
     * @return $this
     */
    public function setYear($year)
    {
        $this->year = $year;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param  User $student
     *
     * @return $this
     */
    public function setStudent($student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubjectCode()
    {
        return $this->subjectCode;
    }

    /**
     * @param  string $subjectCode
     *
     * @return $this
     */
    public function setSubjectCode($subjectCode)
    {
        $this->subjectCode = $subjectCode;
        return $this;
    }

    /**
     * @return array
     */
    public function getGrades()
    {
        return $this->grades;
    }

    /**
     * @param  array $grades
     *
     * @return $this
     */
    public function setGrades($grades)
    {
        $this->grades = $grades;
        return $this;
    }

    /**
     * @return Category
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param  Category $folder
     *
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  mixed $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}
