<?php
/**
 * Representation of a subject within Meriden.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;

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
     * A more detailed description of this involvement
     *
     * @var string
     */
    protected $description;

    /**
     * The student's grades in this group
     *
     * @var array
     */
    protected $grades;

    /**
     * The Schoolbox folder for this tutor group for this student
     *
     * @var Folder
     */
    protected $folder;

    /**
     * The Schoolbox folders for the other classes for this subject
     *
     * @var Folder[]
     */
    protected $cohortFolders;

    /**
     * A grade that is specially named.
     *
     * This is used for the special assessments that are meant to exist for all
     * classes, and are therefore not shown with a class's actual assessments.
     *
     * @var array
     */
    protected $namedGrades;

    /**
     * Moves a grade from $this->grades and puts it in $this->namedGrades.
     *
     * @param string $key
     * @param string $assessmentName
     *
     * @return $this
     */
    public function moveToNamedGrade($key, $assessmentName)
    {
        foreach ($this->grades as $gradeId => $grade) {
            $assessment = $grade['assessment'];
            if ($assessment instanceof IAssessable) {
                $name = $assessment->getTitle();
                if (strcasecmp($name, $assessmentName) === 0) {
                    $this->setNamedGrade($key, $grade);
                    unset($this->grades[$gradeId]);
                }
            }
        }
        return $this;
    }

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
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param  Folder $folder
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

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param  string $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return array
     */
    public function getNamedGrades()
    {
        return $this->namedGrades;
    }

    /**
     * Gets a single named grade by its key.
     *
     * @param string $key
     *
     * @return array
     */
    public function getNamedGrade($key)
    {
        return array_key_exists($key, $this->namedGrades)
            ? $this->namedGrades[$key]
            : array();
    }

    /**
     * Updates a single named grade.
     *
     * @param string $key
     * @param array  $value
     *
     * @return $this
     */
    public function setNamedGrade($key, $value)
    {
        $this->namedGrades[$key] = $value;
        return $this;
    }

    /**
     * @return Folder[]
     */
    public function getCohortFolders()
    {
        return $this->cohortFolders;
    }

    /**
     * @param  Folder[] $cohortFolders
     *
     * @return $this
     */
    public function setCohortFolders($cohortFolders)
    {
        $this->cohortFolders = $cohortFolders;
        return $this;
    }
}
