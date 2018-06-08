<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox;

/**
 * Mapper for rubric groups.
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Group createModel($idValue = null)
 * @method Group findById($id)
 * @method Group[] findByColumnList($columnList, $options = [])
 * @method Group findOneByColumnList($columnList, $order = [], $options = [])
 */
class GroupMapper extends Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_group');
        $this->hasModel(Group::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->uses('grouping', self::TYPE_STRING);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('parent_type', self::TYPE_STRING);

        // Parent ID can reference any of:
        // submission, category, curriculum
        // as far as I can tell, it's never not "category"
        $this->uses('parent_id', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null): bool
    {
        return true;
    }
}
