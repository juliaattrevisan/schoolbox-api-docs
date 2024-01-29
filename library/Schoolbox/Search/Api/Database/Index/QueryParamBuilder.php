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
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [];
        $params = [
            "indexNow$paramKeySuffix"         => Alaress_Date_Format::now()->getDatabase(),
//            "indexKeyword$paramKeySuffix"     => $keyword,
//            "indexKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function buildTitleRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        $keywordMinusPunctuation = preg_replace("#[[:punct:]]#", "", $keyword);
        $quoter = $this->quoter;

        $sqlSumTerms = [
            "IF(si.title = '" . $quoter->escapeForLike($keywordMinusPunctuation) . "', 100, 0)",
            "IF(si.title LIKE '%" . $quoter->escapeForLike($keywordMinusPunctuation) . "%', 50, 0)"
        ];

        $keywordMinusPunctuation = preg_replace("#[[:punct:]]#", "", $keyword);
        $keywordWords = explode(' ', $keywordMinusPunctuation);

        foreach ($keywordWords as $word) {
            if (strlen($word) > 1) {
                $sqlSumTerms[] = "IF(si.title LIKE '%" . $quoter->escapeForLike(trim($word)) . "%', 10, 0)";
            }
        }

        return '(' . implode(' + ', $sqlSumTerms) . ')';
    }

    /**
     * @inheritdoc
     */
    protected function buildContentRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        $quoter = $this->quoter;
        return "MATCH (si.fulltext_index) AGAINST ('" . $quoter->escapeForLike($keyword) . "' IN NATURAL LANGUAGE MODE)";
    }

    /**
     * @inheritdoc
     */
    protected function buildDateRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        $datePart = $this->buildLinearDecayExpression(
            ":indexNow$paramKeySuffix",
            'si.date',
            7,
            56,
        );

        return "IF(si.is_relevance_date_affected, $datePart, 1)";
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
     * This builder may potentially handle any type or subtype.
     *
     * @param string $type
     * @return bool
     */
    protected function handlesType(string $type): bool
    {
        return true;
    }

    /**
     * This builder may potentially handle any type or subtype.
     *
     * @param string $type
     * @return bool
     */
    protected function handlesSubtype(string $type): bool
    {
        return true;
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
        return 'FROM search_index si';
    }
}
