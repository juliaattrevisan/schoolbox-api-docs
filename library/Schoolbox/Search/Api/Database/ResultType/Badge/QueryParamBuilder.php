<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for badge searches.
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
     * * deleted badges are never fetched
     * * hidden badges _are_ fetched (as whether the viewer can view the badge will be determined in the hydrator)
     * * badges whose name or body contain `$keyword` are fetched
     *
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            'b.deleted_at IS NULL'
        ];
        $keywordSearchParts = [
            "b.name LIKE :badgeKeywordLike$paramKeySuffix",
            "b.body LIKE :badgeKeywordLike$paramKeySuffix",
        ];
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params = [
            "badgeNow$paramKeySuffix"         => Alaress_Date_Format::now()->getDatabase(),
            "badgeKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a badge is its updated_at time.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":badgeNow$paramKeySuffix",
            'b.updated_at',
            7,
            56,
        );
    }

    /**
     * Badges are ordered by relevance, as they have an updated_at time.
     *
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The closest thing to a title for a badge is badge.name.
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 'b.name';
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
        return '"' . Hydrator::BASE_TYPE . '"';
    }

    /**
     * The badge's ID is simply its `id` column.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'b.id';
    }

    /**
     * The badge's date is its updated_at date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'b.updated_at';
    }

    /**
     * The badge's creator is badge.created_by.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): ?string
    {
        return 'b.created_by';
    }

    /**
     * The badge table is not linked to the folder table, so return null here.
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): ?string
    {
        return null;
    }

    /**
     * Badges are fetched from the `badge` table.
     *
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM badge b
        ';
    }
}
