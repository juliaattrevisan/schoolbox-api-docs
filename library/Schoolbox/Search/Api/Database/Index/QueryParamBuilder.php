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
            "indexNow$paramKeySuffix"         => Alaress_Date_Format::now()->getDatabase(),
            "indexKeyword$paramKeySuffix"     => $keyword,
            "indexKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":indexNow$paramKeySuffix",
            'si.date',
            7,
            56,
        );
    }

    /**
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'si.date';
    }

    /**
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return "si.title";
    }

    /**
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return "si.created_by";
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
        return "si.type";
    }

    /**
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'si.identifier';
    }

    /**
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'si.folder_id';
    }

    /**
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM search_index si
            INNER JOIN search_index_fulltext sif ON si.identifier = sif.identifier
        ';
    }
}
