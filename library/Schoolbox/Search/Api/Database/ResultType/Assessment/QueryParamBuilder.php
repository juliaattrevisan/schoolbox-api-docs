<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
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
            'assessment.deleted_at IS NULL'
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
        // TODO: Can we do better than hard-coding titles below?
        return "
            COALESCE(
                IF(task.id, 'Task', NULL),
                IF(assess.assessment_id, assess.title, NULL),
                IF(lti_assessment.assessment_id, lti_assessment.lti_assessment_title, NULL),
                IF(submission_box.id, submission_box.name, NULL),
                IF(project.id, 'Project', NULL),
                IF(lesson_plan.id, 'Lesson Plan', NULL)
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
                    IF(task.id, 'task', NULL),
                    IF(assess.assessment_id, 'quiz', NULL),
                    IF(lti_assessment.assessment_id, 'lti', NULL),
                    IF(submission_box.id, 'dueWork', NULL),
                    IF(project.id, 'project', NULL),
                    IF(lesson_plan.id, 'lessonPlan', NULL)
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
            LEFT JOIN lti_assessment ON assessment.id = lti_assessment.assessment_id
            LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
            LEFT JOIN task ON (
                task.assessment_id = assessment.id AND
                task.deleted_at IS NULL
            )
            LEFT JOIN assess ON (
                assess.assessment_id = assessment.id AND
                assess.deleted_at IS NULL
            )
            LEFT JOIN project ON (
                project.assessment_id = assessment.id AND
                project.deleted_at IS NULL
            )
            LEFT JOIN lesson_plan ON (
                lesson_plan.assessment_id = assessment.id AND
                lesson_plan.deleted_at IS NULL
            )
        ';
    }
}
