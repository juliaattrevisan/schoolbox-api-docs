<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Project;

use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Project Mapper
 *
 * @method Model createModel($id=null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('project');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('summator', self::TYPE_STRING);
        $this->joins('owner_id', UserMapper::class);
        $this->joins('mark_type_id', MarkTypeMapper::class, 'mark_type_id', null, null, 'markType');
        $this->joins('assessment_id', AssessmentMapper::class, null, null, null, 'assessmentModel');
        $this->joins('grade_group_id', GradeGroupMapper::class, 'id', null, null, 'gradeGroup');
    }
}
