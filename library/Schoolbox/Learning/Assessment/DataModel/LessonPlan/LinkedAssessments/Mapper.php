<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('lesson_plan_assessment');
        $this->hasModel(Model::class);
        $this->joins(
            'lesson_plan_id',
            \Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper::class,
            'id',
            null,
            self::COLUMN_IDENTIFIER,
            'lessonPlan',
            'lessonPlan'
        );
        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );
    }

    protected function getSelectString()
    {
        return
            "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')},
                {$this->getJoinColumnString('lessonPlan', 'lessonPlan')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel', true)}
            INNER JOIN {$this->getJoinSql('lessonPlan', '', 'lessonPlan', true)}
        ";
    }

    /**
     * Hydrate append assessment and lesson plan objects into the model
     *
     * @param PDOStatement $statement
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Model[] $lessonPlans */
        $lessonPlans = [];

        foreach ($statement as $row) {
            if (!isset($lessonPlans[$row['lesson_plan_id']])) {

                $lessonPlan = $this->hydrate($row);
                $lessonPlan->setLessonPlan($this->hydrateJoin($row, 'lessonPlan', 'lessonPlan'));
                $lessonPlan->setAssessment($this->hydrateJoin($row, 'assessmentModel', 'assessmentModel'));

                /* Stack it up
                 */
                $lessonPlans[$row['lesson_plan_id']] = $lessonPlan;
            }
        }

        return $this->createCollection(array_values($lessonPlans));
    }
}
