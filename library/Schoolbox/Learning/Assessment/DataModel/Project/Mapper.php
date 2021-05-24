<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Project;

use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Project Mapper
 *
 * @method Model createModel($id=null)
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
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
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->joins('owner_id', UserMapper::class);
        $this->joins('mark_type_id', MarkTypeMapper::class, 'mark_type_id', null, null, 'markType');
        $this->joins('assessment_id', Assessment\Mapper::class, 'id', null, null, 'assessmentModel', 'assessment');
        $this->joins('grade_group_id', GradeGroupMapper::class, 'id', null, null, 'gradeGroup', 'grade_group');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteAssessables']);
    }


    /**
     * @param Model $project
     * @return bool
     * @throws BaseMapper\DefinitionException If join $name isn't on this mapper
     * @throws BaseMapper\Exception
     */
    protected function softDeleteAssessables(Model $project): bool
    {
        /** @var Assessment\Mapper $assessmentMapper */
        $assessmentMapper          = $this->getJoinMapper('assessment');
        $assessments               = $assessmentMapper->findByColumn('v_pa.project_id', $project->getId(), ['deleted' => true]);
        $submissionBoxMapper       = SubmissionBoxMapper::getInstance();
        $submissionBoxes           = $submissionBoxMapper->createCollection();
        $otherAssessmentTypeModels = $assessmentMapper->createCollection();

        /* We need to perform some acrobatics here due to the different ways the system treats the
         * DueWork/Assessment relationship. In some parts it's treated as inheritance, in others composition.
         *
         * The distinction is inheritance uses `submission_box.assessment.id` as the entity identifier,
         * whereas composition uses `submission_box.id`.
         *
         * The inheritance-based (eg assessment context) codebase cascades the delete to the assessment model.
         *
         * The composition-based codebase considers the SubmissionBox deleted if it's constituent Assessment is deleted.
         * This means attempting to cascade the delete from [Assessment >> SubmissionBox >> SubmissionBox child relationships] will fail
         * because when the system checks the SubmissionBox, it will already be considered deleted, and the code with short-circuit,
         * failing to cascade to the SubmissionBox child relationships.
         *
         * For now, we'll just compensate for this here.
         */

        foreach ($assessments as $assessment) {
            $submissionBox = $assessment->getDueWork();
            if ($submissionBox) {
                $submissionBoxes[$submissionBox->getId()] = $submissionBox;
            } else {
                $otherAssessmentTypeModels[$assessment->getId()] = $assessment;
            }
        }
        // TODO remove superfluous soft-delete field from other assessment types
        $assessmentMapper->delete($otherAssessmentTypeModels);
        $submissionBoxMapper->delete($submissionBoxes);

        return true;
    }
}
