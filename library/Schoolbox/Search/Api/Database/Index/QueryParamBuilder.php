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
        $keywordSearchParts[] = "(MATCH(si.fulltext_index) AGAINST (:indexKeyword$paramKeySuffix))";
        $keywordSearchParts[] = "(si.text_index LIKE :indexKeywordLike$paramKeySuffix)";
        $whereParts[]         = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params               = [
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
        $relevancePart = $this->buildLinearDecayExpression(
            ":indexNow$paramKeySuffix",
            'si.date',
            7,
            56,
        );
        return "IF(si.is_relevance_date_affected, $relevancePart, 1)";
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
     * By default, a query+param builder handles a type if either:
     * * `getType()` equals `$type`
     * * `$type` starts with `$this->getType()`
     *
     * @param string $type
     * @return bool
     */
    protected function handlesType(string $type): bool
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
