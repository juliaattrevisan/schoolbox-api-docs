<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for indexed searches.
 *
 * This relies on an index already having been built.
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
        $keywordSearchParts = [
            "MATCH(fulltext_index) AGAINST (:indexKeyword$paramKeySuffix)",
            "text_index LIKE :indexKeywordLike$paramKeySuffix",
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            "indexKeyword$paramKeySuffix"     => $keyword,
            "indexKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
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
        return 'relevance';
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
        return 'date';
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
        return "title";
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
        return "created_by";
    }

    /**
     * This accepts all types, and rejects none
     */
    protected function getType(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        return "type";
    }

    /**
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'identifier';
    }

    /**
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'folder_id';
    }

    /**
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return 'FROM search_index';
    }
}
