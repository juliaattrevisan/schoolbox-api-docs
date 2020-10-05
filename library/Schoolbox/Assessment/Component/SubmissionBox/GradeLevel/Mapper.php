<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for due work grade levels.
 */
class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('submission_grade');
        $this->hasModel(Model::class);
        $this->uses('grade_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins(
            'submission_grade_group_id',
            Group\Mapper::class,
            'id',
            null,
            null,
            'group',
            'group'
        );
        $this->uses('lower_bound', self::TYPE_DECIMAL);
        $this->uses('raw_grade', self::TYPE_DECIMAL);
        $this->uses('caption', self::TYPE_STRING);
    }
}
