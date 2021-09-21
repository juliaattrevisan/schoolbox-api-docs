<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Ms\Pastoral\StudentInvolvement\Report\TutorGroup;

use Custom\Ms\Pastoral\StudentInvolvement\Report\Base as BaseReport;
use Schoolbox\User;

/**
 * Base representation of report data for a student's tutor group, independent
 * of any subjects that the student is in.
 */
abstract class Base extends BaseReport
{
    /**
     * The year level coordinator(s) for this group
     *
     * @var User[]
     */
    protected $coordinators = array();

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
     * By default, tutor group reports have subject reports.
     *
     * @return bool
     */
    public function hasSubjectReports(): bool
    {
        return true;
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

    /**
     * @return \Schoolbox\User[]
     */
    public function getCoordinators()
    {
        return $this->coordinators;
    }

    /**
     * @param  \Schoolbox\User[] $coordinators
     *
     * @return $this
     */
    public function setCoordinators($coordinators)
    {
        $this->coordinators = $coordinators;
        return $this;
    }
}
