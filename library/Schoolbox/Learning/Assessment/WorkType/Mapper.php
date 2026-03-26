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
    public const string VISIBILITY_STAFF_ONLY = 'staffOnly';
    public const string VISIBILITY_STAFF_STUDENTS = 'staffAndStudents';
    public const string VISIBILITY_STAFF_PARENTS = 'staffAndParents';
    public const string VISIBILITY_STAFF_STUDENTS_PARENTS = 'staffAndStudentsAndParents';

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
        $this->uses('staff_or_instructor_visible_only', self::TYPE_BOOLEAN);
        $this->uses('is_visible_to_parents', self::TYPE_BOOLEAN);
        $this->uses('is_visible_to_students', self::TYPE_BOOLEAN);
        $this->uses('permitted_project', self::TYPE_BOOLEAN);
        $this->uses('permitted_due_work', self::TYPE_BOOLEAN);
        $this->uses('permitted_task', self::TYPE_BOOLEAN);
        $this->uses('permitted_quiz', self::TYPE_BOOLEAN);
        $this->uses('permitted_lti', self::TYPE_BOOLEAN);
        $this->uses('permitted_lesson_plan', self::TYPE_BOOLEAN);
        $this->uses('permitted_dialog_agent', self::TYPE_BOOLEAN);

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
