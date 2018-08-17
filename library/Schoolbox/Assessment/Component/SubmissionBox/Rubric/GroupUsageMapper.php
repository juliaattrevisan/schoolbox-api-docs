<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

/**
 * Mapper for usage of a rubric group on a due work.
 *
 * @method GroupUsage createModel($idValue = null)
 * @method GroupUsage[]|Schoolbox\GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class GroupUsageMapper extends Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_group_usage');
        $this->hasModel(GroupUsage::class);
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins(
            'rubric_group_id',
            GroupMapper::class,
            'id',
            null,
            null,
            'group',
            'group'
        );
        $this->joins(
            'submission_box_id',
            SubmissionBoxMapper::class,
            'id',
            null,
            null,
            'submissionBox',
            'submission_box'
        );
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
