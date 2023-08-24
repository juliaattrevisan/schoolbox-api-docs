<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\File;

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
     * * deleted links are never fetched
     * * hidden links _are_ fetched (as whether the viewer can view the link will be determined in the hydrator)
     * * links whose url or description contain `$keyword` are fetched
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'l.deleted_at IS NULL',
            'fo.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            'l.description LIKE :linkKeywordLike',
            'l.url LIKE :linkKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'linkNow'         => Alaress_Date_Format::now()->getDatabase(),
            'linkKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a link is its modified time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':linkNow',
            'l.modified',
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
     * The link's title is its `description`.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'l.description';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A link's type is always 'Link'.
     *
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return "'Link'";
    }

    /**
     * The link's ID is simply in its `id` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 'l.id';
    }

    /**
     * The link's date is its modified date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 'l.modified';
    }

    /**
     * The link's creator is its `owner` column.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return 'l.owner';
    }

    /**
     * The link's folder is its `category` column.
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return 'l.category';
    }

    /**
     * Links are fetched from the `link` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM link l
            INNER JOIN folder fo ON l.category = fo.id
        ';
    }
}
