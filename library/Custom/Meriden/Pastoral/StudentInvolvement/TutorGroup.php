<?php
/**
 * Representation of a student's involvement within a tutor group.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\User;
use Schoolbox\Resource\Category;

class TutorGroup
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
     * The Schoolbox folder for this tutor group for this student
     *
     * @var Category
     */
    protected $folder;

    /**
     * The tutors for this group
     *
     * @var array
     */
    protected $tutors;

    /**
     * The external tutor group entry
     *
     * @var array
     */
    protected $tutorGroup;

    /**
     * The activities this student has partaken in within this group
     *
     * @var array
     */
    protected $activities;

    /**
     * The student's attendances
     *
     * @var array
     */
    protected $attendances;

    /**
     * The student's grades in this group
     *
     * @var array
     */
    protected $grades;

    /**
     * The subjects which the student was a part of as part of this tutor group
     *
     * The subject list will not be fully hydrated - only the year and subject
     * code will be.
     *
     * @var array|Subject[]
     */
    protected $subjects;

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
     * @return array
     */
    public function getTutors()
    {
        return $this->tutors;
    }

    /**
     * @param  array $tutors
     *
     * @return $this
     */
    public function setTutors($tutors)
    {
        $this->tutors = $tutors;
        return $this;
    }

    /**
     * @return array
     */
    public function getActivities()
    {
        return $this->activities;
    }

    /**
     * @param  array $activities
     *
     * @return $this
     */
    public function setActivities($activities)
    {
        $this->activities = $activities;
        return $this;
    }

    /**
     * @return array
     */
    public function getAttendances()
    {
        return $this->attendances;
    }

    /**
     * @param  array $attendances
     *
     * @return $this
     */
    public function setAttendances($attendances)
    {
        $this->attendances = $attendances;
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
     * @return array|Subject[]
     */
    public function getSubjects()
    {
        return $this->subjects;
    }

    /**
     * @param  array|Subject[] $subjects
     *
     * @return $this
     */
    public function setSubjects($subjects)
    {
        $this->subjects = $subjects;
        return $this;
    }

    /**
     * @return array
     */
    public function getTutorGroup()
    {
        return $this->tutorGroup;
    }

    /**
     * @param  array $tutorGroup
     *
     * @return $this
     */
    public function setTutorGroup($tutorGroup)
    {
        $this->tutorGroup = $tutorGroup;
        return $this;
    }
}
