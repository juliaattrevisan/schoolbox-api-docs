<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\WorkType;

use Schoolbox;

/**
 * @method Model findById($identifierValue)
 * @method Model createModel($idValue = null)
 * @method Schoolbox\GenericCollection|Model[] findAll($options = [])
 */
class Mapper extends Schoolbox\Mapper
{
    /**
     * The docblocks in this file define how work type visibility should work in Schoolbox.
     *
     * Glossary:
     *  - Instructor: A user with WRITE+ access to the assessment folder.
     *  - Learner: A user with READ access to the assessment folder.  (Note this is a folder-level concept.)
     *  - Participant: A user for whom IAssessment->getParticipantIfExtant() returns a non-null participant.
     *                 (Note this is an assessment-level concept.)
     *  - Learner participant: A learner who is also a participant.
     *  - Parent of learner participant: A parent whose child is a learner participant.
     *
     * Visibility of assessments also depends on:
     *  - Whether they are published (only instructors and staff should see unpublished assessments)
     *  - Whether they are closed and whether closed assessments should be visible.
     *
     * @see implementation in the Learning\Assessment\Visibility class.
     */

    /**
     * Visibility 'Staff and instructors only'
     *  - Instructors - always included
     *  - Learner participants - always excluded
     *  - Parents of learner participants - included only if instructors
     *  - Users of role type 'staff' - included unless they are learner participants
     *  - Other users of any role type - included only if instructors
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS = 'staffAndInstructorsOnly';
    /**
     * Visibility 'Staff and instructors and learners'
     *  - Instructors - always included
     *  - Learner participants - always included
     *  - Parents of learner participants - included only if instructors or learner participants
     *  - Users of role type 'staff' - always included
     *  - Other users of any role type - included only if instructors or learner participants
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS_LEARNERS = 'staffAndInstructorsAndLearners';
    /**
     * Visibility 'Staff and instructors and parents'
     *  - Instructors - always included
     *  - Learner participants - always excluded
     *  - Parents of learner participants - included unless they are learner participants
     *  - Users of role type 'staff' - included unless they are learner participants
     *  - Other users of any role type - included only if instructors
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS_PARENTS = 'staffAndInstructorsAndParents';
    /**
     * Visibility 'Staff and instructors and learners and parents'
     *  - Instructors - always included
     *  - Learner participants - always included
     *  - Parents of learner participants - always included
     *  - Users of role type 'staff' - always included
     *  - Other users of any role type - included only if instructors or learner participants
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS = 'staffAndInstructorsAndLearnersAndParents';

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('lu_work_type');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('external_result_type', self::TYPE_STRING);
        $this->uses('show_on_grades_page', self::TYPE_BOOLEAN);
        $this->uses('is_visible_to_parents', self::TYPE_BOOLEAN);
        $this->uses('is_visible_to_learners', self::TYPE_BOOLEAN);
        $this->uses('permitted_project', self::TYPE_BOOLEAN);
        $this->uses('permitted_due_work', self::TYPE_BOOLEAN);
        $this->uses('permitted_task', self::TYPE_BOOLEAN);
        $this->uses('permitted_quiz', self::TYPE_BOOLEAN);
        $this->uses('permitted_lti', self::TYPE_BOOLEAN);
        $this->uses('permitted_lesson_plan', self::TYPE_BOOLEAN);
        $this->uses('permitted_dialog_agent', self::TYPE_BOOLEAN);
        $this->uses('permitted_reporting_activity', self::TYPE_BOOLEAN);

        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
