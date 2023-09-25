<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
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
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
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
            'assessment.title LIKE :assessmentKeywordLike',
            'assessment.description LIKE :assessmentKeywordLike',
            'task.link LIKE :assessmentKeywordLike',
            'assess.title LIKE :assessmentKeywordLike',
            'assess.description LIKE :assessmentKeywordLike',
            'lti_assessment.lti_assessment_url LIKE :assessmentKeywordLike',
            'lti_assessment.lti_assessment_title LIKE :assessmentKeywordLike',
            'lti_assessment.lti_assessment_text LIKE :assessmentKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'assessmentNow'         => Alaress_Date_Format::now()->getDatabase(),
            'assessmentKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a assessment is its updated_at time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':assessmentNow',
            'assessment.updated_at',
            7,
            56,
        );
    }

    /**
     * Links are ordered by relevance, as they have a modified time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The title for an assessment depends on its type.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return "
            COALESCE(
                IF(task.id, assessment.title, NULL),
                IF(assess.assessment_id, assess.title, NULL),
                IF(lti_assessment.assessment_id, lti_assessment.lti_assessment_title, NULL),
                IF(submission_box.id, submission_box.name, NULL),
                IF(project.id, assessment.title, NULL),
                IF(lesson_plan.id, assessment.title, NULL)
            )
        ";
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * @return string
     */
    protected function buildTypeExpression(): string
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
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 'assessment.id';
    }

    /**
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 'assessment.updated_at';
    }

    /**
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return null; // No assessment.created_by column.
    }

    /**
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return 'mv_assessment_relation.folder_id';
    }

    /**
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM mv_assessment_relation
            JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
            JOIN folder ON (
                mv_assessment_relation.folder_id = folder.id
            )
            LEFT JOIN project ON (
                mv_assessment_relation.project_assessment_id = project.id
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
