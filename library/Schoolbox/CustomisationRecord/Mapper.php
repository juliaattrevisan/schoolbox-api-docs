<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\Assessment\Participant\Model;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Filter\Filters\CustomisationRecord;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use PDO;

class Mapper extends BaseMapper
{
    public const int DEFAULT_LIMIT = 20;

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
            SELECT DISTINCT {$this->getColumnString()},
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
            INNER JOIN userCampus sc ON u.id = sc.uid
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
            LEFT JOIN differentiation_profiles ON differentiation_profiles.student_id = u.id
            LEFT JOIN differentiation_profiles_adjustment_category ac ON FIND_IN_SET(ac.id, differentiation_profiles.adjustment_category)
            LEFT JOIN differentiation_profiles_adjustment_level al ON FIND_IN_SET(al.id, differentiation_profiles.adjustment_level)

        ";
    }

    public function findByFilters(
        ?array $nameSearch,
        ?array $yearLevelIds,
        ?array $campusIds,
        ?array $adjustmentCategories,
        ?array $adjustmentLevels,
        ?array $termIds,
        ?array $cursorParams = null,
        ?array $order = null,
        ?int $limit = null
    ): GenericCollection {
        $pb = $this->getPredicateBuilder();
        if (!empty($nameSearch)) {
            $pb = $this->buildNameSearchPredicate($pb, $nameSearch);
        }

        if (!empty($yearLevelIds)) {
            $pb = $this->buildYearLevelPredicate($pb, $yearLevelIds);
        }

        if (!empty($termIds)) {
            $pb = $this->buildTermPredicate($pb, $termIds);
        }

        if (!empty($campusIds)) {
            $pb = $pb->in('sc.campus', $campusIds);
        }

        if (!empty($adjustmentCategories)) {
            $categoriesPlaceholders = Builder::getArrayPlaceholders($adjustmentCategories, 'adjustmentCategory');
            $pb = $pb->rawWhere("COALESCE(ac.id, :adjustment_categories_none_key) IN $categoriesPlaceholders", [
                'adjustment_categories_none_key' => CustomisationRecord\AdjustmentCategory\KeyConverter::NONE_KEY_VALUE,
                ...Builder::getArrayParameters($adjustmentCategories, 'adjustmentCategory'),
            ]);
        }

        if (!empty($adjustmentLevels)) {
            $levelsPlaceholders = Builder::getArrayPlaceholders($adjustmentLevels, 'adjustmentLevel');
            $pb = $pb->rawWhere("COALESCE(al.id, :adjustment_levels_none_key) IN $levelsPlaceholders", [
                'adjustment_levels_none_key' => CustomisationRecord\AdjustmentLevel\KeyConverter::NONE_KEY_VALUE,
                ...Builder::getArrayParameters($adjustmentLevels, 'adjustmentLevel'),
            ]);
        }

        if (!empty($cursorParams)) {
            $pb = $this->applyCursor(
                $pb,
                $cursorParams,
                $order ?? $this->getDefaultOrderByParams()
            );
        }

        $option = [];

        if (null !== $limit) {
            $option['limit'] = $limit;
        }

        if (!empty($order)) {
            $option['order'] = $order;
        }

        return $this->findByPredicates(
            $pb,
            $option,
        );
    }

    /**
     * Build name search predicate
     * @param Builder $pb
     * @param array $nameSearch
     * @return Builder
     */
    private function buildNameSearchPredicate(Builder $pb, array $nameSearch): Builder
    {
        // Just use the first one, and split it into tokens
        foreach ($nameSearch as $nameSearchString => $_) {
            $names = array_map('trim', preg_split('/\\s+/', (string)$nameSearchString));
            foreach ($names as $name) {
                if (strlen($name) > 0 && $name !== CustomisationRecord\Name\Definition::KEY_ALL) {
                    $pb = $pb->any(
                        $this->getPredicateBuilder()->like(
                            'u.chosen_name',
                            '%' . $this->getDatabase()->getQuoter()->escapeForLike($name) . '%'
                        )->like(
                            'u.lastname',
                            '%' . $this->getDatabase()->getQuoter()->escapeForLike($name) . '%'
                        )
                    );
                }
            }
        }
        return $pb;
    }

    /**
     * Apply cursor predicates to the predicate builder based on the provided cursor params and order
     *
     * @param Builder $pb
     * @param array $cursorParams
     * @param array $order
     * @return Builder
     */
    private function applyCursor(Builder $pb, $cursorParams, $order): Builder
    {
        /* Build up the cursor predicates
         * This is synonymous to how we do it in Alaress\Database\Cursor\MapperCursor
         */
        $columnBuilders         = [];
        $previousColumnBuilders = [];
        foreach ($order as $column => $direction) {
            $value = $cursorParams[$column] ?? null;

            if ($direction === self::SORT_ORDER_ASC) {
                $thisBuilder = $pb->emptyClone()->greaterThan($column, $value);
            } else if ($direction === self::SORT_ORDER_DESC) {
                $thisBuilder = $pb->emptyClone()->lessThan($column, $value);
            } else {
                continue;
            }

            $columnBuilder = $pb->emptyClone();
            foreach ($previousColumnBuilders as $previousColumnBuilder) {
                $columnBuilder = $columnBuilder->all($previousColumnBuilder);
            }
            $columnBuilders[] = $columnBuilder->all($thisBuilder);

            $previousColumnBuilders[] = $pb->emptyClone()->equals($column, $value);
        }

        // Put each column builder into the main predicate builder
        $subBuilder = $pb->emptyClone();
        foreach ($columnBuilders as $columnBuilder) {
            // All the builders in the column builder must match
            $subBuilder = $subBuilder->all($columnBuilder);
        }
        // Any of the column builders may match
        $pb = $pb->any($subBuilder);
        return $pb;
    }

    /**
     * Build year level predicate
     * @param Builder $pb
     * @param array $yearLevelIds
     * @return Builder
     */
    private function buildYearLevelPredicate(Builder $pb, array $yearLevelIds): Builder
    {
        $yearlevelPlaceholders = Builder::getArrayPlaceholders($yearLevelIds, 'yearLevelId');
        $pb = $pb->rawWhere("COALESCE(u.year_id, :year_levels_none_key) IN $yearlevelPlaceholders", [
            'year_levels_none_key' => CustomisationRecord\YearLevel\KeyConverter::NONE_KEY_VALUE,
            ...Builder::getArrayParameters($yearLevelIds, 'yearLevelId'),
        ]);
        return $pb;
    }

    /**
     * Build term predicate
     * @param Builder $pb
     * @param array $termIds
     * @return Builder
     */
    private function buildTermPredicate(Builder $pb, array $termIds): Builder
    {
        $terms = \Schoolbox\Assessment\Term\Mapper::getInstance()->findByColumnList(
            [
                'id' => $termIds,
            ],
            [
                'order' => ['start' => self::SORT_ORDER_ASC],
            ]
        );
        $termPb = $this->getPredicateBuilder();
        /** @var \Schoolbox\Assessment\Term\Model $term */
        foreach ($terms as $term) {
            $tempPb = $this->getPredicateBuilder()
                ->greaterThanOrEqualTo('created_at', $term->getStart()->getDatabase());

            if (null !== $term->getActualFinish()) {
                $tempPb->lessThanOrEqualTo('created_at', $term->getActualFinish()->getDatabase());
            }
            $termPb->all($tempPb);
        }
        $pb->any($termPb);
        return $pb;
    }
}
