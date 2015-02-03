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
}
