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
     * Staff and instructors only
     *  - instructors - always included
     *  - learners - always excluded
     *  - parents of learners - included only if instructors
     *  - users of role type 'staff' - included unless they are learners
     *  - other users of any role type - included only if instructors
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS = 'staffAndInstructorsOnly';
    /**
     * Staff and instructors and learners
     *  - instructors - always included
     *  - learners - always included
     *  - parents of learners - included only if instructors or learners
     *  - users of role type 'staff' - always included
     *  - other users of any role type - included only if instructors or learners
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS_LEARNERS = 'staffAndInstructorsAndLearners';
    /**
     * Staff and instructors and parents
     *  - Instructors - always included
     *  - learners - always excluded
     *  - parents of learners - included unless they are learners
     *  - users of role type 'staff' - included unless they are learners
     *  - other users of any role type - included only if instructors
     */
    public const string VISIBILITY_STAFF_INSTRUCTORS_PARENTS = 'staffAndInstructorsAndParents';
    /**
     * Staff and instructors and learners and parents
     *  - instructors - always included
     *  - learners - always included
     *  - parents of learners - always included
     *  - users of role type 'staff' - always included
     *  - other users of any role type - included only if instructors or learners
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

    /**
     * This function performs the same operation as WorkType\Model::getVisibility(), but does not require a model
     * instance to be passed in.  Use this when a model instance is not available.
     *
     * @param bool $isVisibleToParents
     * @param bool $isVisibleToLearners
     * @return string
     */
    public function getVisibilityFromBooleans(bool $isVisibleToParents, bool $isVisibleToLearners): string
    {
        return match ([$isVisibleToParents, $isVisibleToLearners]) {
            [false, false] => self::VISIBILITY_STAFF_INSTRUCTORS,
            [false, true ] => self::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS,
            [true , false] => self::VISIBILITY_STAFF_INSTRUCTORS_PARENTS,
            [true , true ] => self::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS,
        };
    }
}
