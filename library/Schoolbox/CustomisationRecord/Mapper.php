<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use PDOStatement;
use Schoolbox\Assessment\Participant\Model;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use PDO;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('participant');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('participant_plan_id', PlanMapper::class, 'id', null, null, 'plan', 'plan');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('excluded_at', self::TYPE_DATETIME);
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $participant = $this->hydrate($row);
            $planMapper = $this->getJoinMapper('plan');
            $assessmentMapper = $planMapper->getJoinMapper('assessment');
            /* Hydrate plan with the assessment details too
             */
            $plan = $planMapper->hydrate($row, 'pp');
            /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessment */
            $assessment = $assessmentMapper->hydrate($row, 'a');
            if ($assessment->getOriginalAssessmentId() !== null) {
                $assessment->setOriginalAssessment(
                    $assessmentMapper->hydrate($row, 'oa')
                );
            }
            /* Hydrating the unit implementation and course implementation for the assessment
             */
            /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $assessmentContext */
            $assessmentContext = $assessmentMapper->hydrateJoin($row, 'assessment_course_unit_implementation', 'cui');
            /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Model $courseImplementation */
            $courseImplementation = $assessmentMapper->hydrateJoin($row, 'assessment_course_unit_implementation.course', 'ci');
            if ($courseImplementation !== null) {
                $folder = $assessmentMapper->hydrateJoin($row, 'assessment_course_unit_implementation.course.folder', 'f');
                /* Hydrating folder for class details
                 */
                $courseImplementation->setFolder($folder);
                $assessmentContext->setCourse($courseImplementation);
            }
            $assessment->setAssessmentContext($assessmentContext);
            $plan->setAssessment($assessment);
            /* Hydrate user
             */
            $user = $this->getJoinMapper('user')->hydrate($row, 'u');

            $participant->setPlan($plan);
            if (null !== $user) {
                $participant->setUser($user);
            }
            $collection[$participant->getId()] = $participant;
        }

        return $collection;
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('plan', 'pp')},
                   {$this->getJoinColumnString('user', 'u')},
                   {$this->getJoinColumnString('plan.assessment', 'a')},
                   {$this->getJoinColumnString('plan.assessment', 'oa')},
                   {$this->getJoinColumnString('plan.assessment.assessment_course_unit_implementation', 'cui')},
                   {$this->getJoinMapper('plan.assessment.assessment_course_unit_implementation')->getJoinColumnString('course', 'ci')},
                   {$this->getJoinMapper('plan.assessment.assessment_course_unit_implementation')->getJoinColumnString('course.folder', 'f')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        $staticPlan = PlanMapper::PLAN_TYPE_STATIC;
        $customParticipantPlan = PlanMapper::PLAN_TYPE_CUSTOM_PARTICIPATION;
        return "
          FROM   {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('plan', '', 'pp')}
            AND (pp.is_dynamic = '{$staticPlan}' OR pp.is_dynamic = '{$customParticipantPlan}')
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN assessment a ON pp.assessment_id = a.id AND a.deleted_at IS NULL
            LEFT JOIN (
                SELECT p.assessment_id projectAssessmentId, pa.assessment_id
                FROM project_assessment pa
                     INNER JOIN project p ON p.id = pa.project_id AND p.deleted_at IS NULL
            ) pa ON a.id = pa.assessment_id
            LEFT JOIN assessment_course_unit_implementation ui ON a.id = ui.assessment_id or pa.projectAssessmentId = ui.assessment_id
            LEFT JOIN course_unit_implementation cui ON ui.course_unit_implementation_id = cui.id
            LEFT JOIN course_implementation ci ON cui.course_implementation_id = ci.id
            LEFT JOIN folder f ON ci.folder_id = f.id
            LEFT JOIN assessment oa ON a.original_assessment_id = oa.id
        ";
    }
}
