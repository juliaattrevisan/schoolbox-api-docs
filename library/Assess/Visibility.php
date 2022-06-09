<?php

/**
 * Visibility
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Assess;

use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Determine visibility for an assessment.
 *
 * @deprecated use `\Schoolbox\Learning\Assessment\IAssessable::isFeedbackVisibleToUser` instead
 */
class Visibility
{
    /**
     * Identifier
     *
     * @var int|null
     */
    private $id = null;

    /**
     * Name of the visibility status
     *
     * @var string
     */
    private $name = '';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Get a list of visibility consts
     *
     * This can prevent Quiz marks from appearing in Report Cards, from being
     * shown to the participant at the end, etc
     *
     * Note that the assess_visibility table has been made synonymous with the
     * consts defined in SubmissionBox (zero-based) and thus won't autoincrement
     *
     * @param \Schoolbox\User $student     The student whose visibility we're checking
     * @param \Schoolbox\User $currentUser The user who's viewing the student
     * @return array
     */
    public function getVisibilityArray(\Schoolbox\User $student, \Schoolbox\User $currentUser)
    {
        $visibilities = array();
        if ($currentUser->isSuperuser() || $currentUser->getRole()->isStaff()) {
            $visibilities[] = IAssessable::MARK_VISIBILITY_PRIVATE;
            $visibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
            $visibilities[] = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
        } else if ($currentUser->getRole()->isStudent()) {
            $visibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
            $visibilities[] = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
        } else {
            $student->loadParents();
            if ($currentUser->isParentOf($student)) {
                $visibilities[] = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
            }
        }

        return $visibilities;
    }

    /**
     * Is this Quiz mark visible to staff only
     *
     * @return bool
     */
    public function isStaff()
    {
        if ($this->id == IAssessable::MARK_VISIBILITY_PRIVATE) {
            return true;
        }

        return false;
    }

    /**
     * Is this Quiz mark visible to staff and students
     *
     * @return bool
     */
    public function isStaffStudent()
    {
        if ($this->id == IAssessable::MARK_VISIBILITY_STUDENT) {
            return true;
        }

        return false;
    }

    /**
     * Is this Quiz mark visible to staff and students
     *
     * @return bool
     */
    public function isStaffStudentParent()
    {
        if ($this->id == IAssessable::MARK_VISIBILITY_PARENT_STUDENT) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set id
     *
     * @param int|null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
