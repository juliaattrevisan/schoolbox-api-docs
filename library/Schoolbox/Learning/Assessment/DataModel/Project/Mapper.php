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
        $this->joins('assessment_id', AssessmentMapper::class, null, null, null, 'assessmentModel', 'assessment');
        $this->joins('grade_group_id', GradeGroupMapper::class, 'id', null, null, 'gradeGroup', 'grade_group');
        $this->uses('deleted_at', self::TYPE_TIMESTAMP, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteAssessables']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreAssessables']);
    }

    /**
     * @param Model $project
     * @return bool
     */
    protected function softDeleteAssessables(Model $project)
    {
        $assessmentMapper = $this->getJoinMapper('assessment');
        $assessments = $assessmentMapper->findByColumn('v_pa.project_id', $project->getId(), ['deleted' => true]);
        $assessmentMapper->delete($assessments);
        return true;
    }

    /**
     * @param Model $project
     * @return bool
     */
    protected function restoreAssessables(Model $project)
    {
        $assessmentMapper = $this->getJoinMapper('assessment');
        $assessments = $assessmentMapper->findByColumn('v_pa.project_id', $project->getId(), ['deleted' => true]);
        $assessmentMapper->restoreSoftDeleted($assessments);
        return true;
    }
}
