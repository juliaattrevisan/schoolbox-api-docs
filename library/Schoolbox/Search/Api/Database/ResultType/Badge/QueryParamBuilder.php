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
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'b.deleted_at IS NULL'
        ];
        $keywordSearchParts = [
            'b.name LIKE :badgeKeywordLike',
            'b.body LIKE :badgeKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'badgeNow'         => Alaress_Date_Format::now()->getDatabase(),
            'badgeKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a badge is its updated_at time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':badgeNow',
            'b.updated_at',
            7,
            56,
        );
    }

    /**
     * Badges are ordered by relevance, as they have an updated_at time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The closest thing to a title for a badge is badge.name.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'b.name';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A badge's type is always 'badge'.
     *
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return "'badge'";
    }

    /**
     * The badge's ID is simply its `id` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 'b.id';
    }

    /**
     * The badge's date is its updated_at date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 'b.updated_at';
    }

    /**
     * The badge's creator is badge.created_by.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return 'b.created_by';
    }

    /**
     * The badge table is not linked to the folder table, so return null here.
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return null;
    }

    /**
     * Badges are fetched from the `badge` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM badge b
        ';
    }
}
