<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Link;

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
     * * links whose url or comment or description contain `$keyword` are fetched
     *
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts         = [
            'l.deleted_at IS NULL',
            'fo.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            "MATCH(l.url, l.description, l.comment) AGAINST (:linkKeyword$paramKeySuffix IN BOOLEAN MODE)",
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            "linkNow$paramKeySuffix"     => Alaress_Date_Format::now()->getDatabase(),
            "linkKeyword$paramKeySuffix" => $keyword,
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a link is its modified time.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":linkNow$paramKeySuffix",
            'l.modified',
            7,
            56,
        );
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
     * The closest thing to a title for a link is link.description.
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 'l.description';
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
     * The link's ID is simply in its `id` column.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'l.id';
    }

    /**
     * The link's date is its modified date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'l.modified';
    }

    /**
     * The link's creator is its `owner` column.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return 'l.owner';
    }

    /**
     * The link's folder is its `category` column.
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'l.category';
    }

    /**
     * Links are fetched from the `link` table.
     *
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM link l
            INNER JOIN folder fo ON l.category = fo.id
        ';
    }
}
