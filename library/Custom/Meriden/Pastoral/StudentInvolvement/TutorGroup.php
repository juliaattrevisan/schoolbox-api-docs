<?php
/**
 * Representation of a student's involvement within a tutor group.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

class TutorGroup extends Involvement
{
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
     * The subjects which the student was a part of as part of this tutor group
     *
     * The subject list will not be fully hydrated - only the year and subject
     * code will be.
     *
     * @var array
     */
    protected $subjects;

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
    public function getSubjects()
    {
        return $this->subjects;
    }

    /**
     * @param  array $subjects
     *
     * @return $this
     */
    public function setSubjects($subjects)
    {
        $this->subjects = $subjects;
        return $this;
    }
}
