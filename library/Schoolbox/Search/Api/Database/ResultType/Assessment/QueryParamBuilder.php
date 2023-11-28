<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for link searches.
 */
class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * Constructor
     *
     * @param IQuoter $quoter
     */
    public function __construct(private IQuoter $quoter)
    {
    }

    /**
     * Build WHERE params such that
     * * deleted assessments are never fetched
     * * hidden assessments _are_ fetched (as whether the viewer can view the assessment will be determined in the hydrator)
     * * assessments whose url or comment or description contain `$keyword` are fetched
     *
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            // If any higher-level row the assessment is linked to is deleted, treat the assessment as deleted.
            'assessment.deleted_at IS NULL AND
             folder.deleted_at IS NULL AND
             project.deleted_at IS NULL AND
             course_implementation.deleted_at IS NULL AND
             course_unit_implementation.deleted_at IS NULL AND
             course_specification.deleted_at IS NULL AND
             course_unit_specification.deleted_at IS NULL AND
             task.deleted_at IS NULL AND
             assess.deleted_at IS NULL AND
             lesson_plan.deleted_at IS NULL
            '
        ];
        $keywordSearchParts = [
            /**
             * NOTES:
             *  - Checks made in the Hydrator must match the checks made here.
             *    @see Assessment\Hydrator->assessmentMatchesKeywordOnlyOnDescription().
             *  - lesson_plan_field.visibility is checked in the Hydrator.
             */
            "assessment.title LIKE :assessmentKeywordLike$paramKeySuffix",
            "assessment.description LIKE :assessmentKeywordLike$paramKeySuffix",
            "task.link LIKE :assessmentKeywordLike$paramKeySuffix",
            "assess.title LIKE :assessmentKeywordLike$paramKeySuffix",
            "assess.description LIKE :assessmentKeywordLike$paramKeySuffix",
            "lti_assessment.lti_assessment_url LIKE :assessmentKeywordLike$paramKeySuffix",
            "lti_assessment.lti_assessment_title LIKE :assessmentKeywordLike$paramKeySuffix",
            "lti_assessment.lti_assessment_text LIKE :assessmentKeywordLike$paramKeySuffix",
            "EXISTS(
                 SELECT *
                 FROM lesson_plan_field
                 WHERE lesson_plan_field.lesson_plan_id = lesson_plan.id
                 AND lesson_plan_field.deleted_at IS NULL
                 AND (
                    title LIKE :assessmentKeywordLike$paramKeySuffix OR
                    body LIKE :assessmentKeywordLike$paramKeySuffix
                 )
             )"
        ];
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params       = [
            "assessmentNow$paramKeySuffix"         => Alaress_Date_Format::now()->getDatabase(),
            "assessmentKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * Use due_date as the relevancy.
     * Items due today should appear closer to the top than items due 6 months from now.
     * Note 'specification' assessments don't have a due_date, so we fall back to updated_at.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":assessmentNow$paramKeySuffix",
            'COALESCE(assessment.due_date, assessment.updated_at)',
            7,
            56,
        );
    }

    /**
     * Use due_date for the date range filter.
     * Items due within the specified date range should appear regardless of created_at or updated_at.
     * Note 'specification' assessments don't have a due_date, so we fall back to updated_at.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'COALESCE(assessment.due_date, assessment.updated_at)';
    }

    /**
     * Links are ordered by relevance, as they have a modified time.
     *
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The title for an assessment depends on its type.
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        // Note on redundancy of assessment titles:
        // assess.title, submission_box.title, and lti_assessment.lti_assessment_title should always match assessment.title for linked rows.
        return "assessment.title";
    }

    /**
     * Assessments have creators (except lti_assessments), but they are set on the tables below assessment.
     * The creator probably should be specified in the `assessment` table, but moving all those fields up one level might be a bit hard.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        // All second-level db tables have a creator field except lti_assessment.
        return "
            COALESCE(
                IF(task.id, task.user_id, NULL),
                IF(assess.assessment_id, assess.user_id, NULL),
                IF(submission_box.id, submission_box.owner, NULL),
                IF(project.id, project.owner_id, NULL),
                IF(lesson_plan.id, lesson_plan.created_by, NULL),
                IF(lti_assessment.assessment_id, NULL, NULL)
            )
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        return "
            CONCAT(
                '" . Hydrator::BASE_TYPE . ".',
                COALESCE(
                    IF(task.id, '" . AssessmentType::TYPE_TASK . "', NULL),
                    IF(assess.assessment_id, '" . AssessmentType::TYPE_QUIZ . "', NULL),
                    IF(lti_assessment.assessment_id, '" . AssessmentType::TYPE_LTI_ASSESSMENT . "', NULL),
                    IF(submission_box.id, '" . AssessmentType::TYPE_DUE_WORK . "', NULL),
                    IF(project.id, '" . AssessmentType::TYPE_PROJECT . "', NULL),
                    IF(lesson_plan.id, '" . AssessmentType::TYPE_LESSON_PLAN . "', NULL)
                )
            )
        ";
    }

    /**
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'assessment.id';
    }

    /**
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'mv_assessment_relation.folder_id';
    }

    /**
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM mv_assessment_relation
            JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
            JOIN folder ON (
                mv_assessment_relation.folder_id = folder.id
            )
            LEFT JOIN course_implementation ON (
                mv_assessment_relation.course_implementation_id = course_implementation.id
            )
            LEFT JOIN course_unit_implementation ON (
                mv_assessment_relation.course_unit_implementation_id = course_unit_implementation.id
            )
            LEFT JOIN course_specification ON (
                mv_assessment_relation.course_specification_id = course_specification.id
            )
            LEFT JOIN course_unit_specification ON (
                mv_assessment_relation.course_unit_specification_id = course_unit_specification.id
            )
            LEFT JOIN project ON (
                assessment.id=project.assessment_id
            )
            LEFT JOIN lti_assessment ON (
                assessment.id = lti_assessment.assessment_id
            )
            LEFT JOIN submission_box ON (
                assessment.id = submission_box.assessment_id
            )
            LEFT JOIN task ON (
                task.assessment_id = assessment.id
            )
            LEFT JOIN assess ON (
                assess.assessment_id = assessment.id
            )
            LEFT JOIN lesson_plan ON (
                lesson_plan.assessment_id = assessment.id
            )
        ';
    }
}
