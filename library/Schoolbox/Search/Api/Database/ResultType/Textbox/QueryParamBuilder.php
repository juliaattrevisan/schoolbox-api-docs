<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for textbox searches.
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
     * * deleted textboxes are never fetched
     * * hidden textboxes _are_ fetched (as whether the viewer can view the textbox will be determined in the hydrator)
     * * textboxes whose url or comment or description contain `$keyword` are fetched
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'tb.deleted_at IS NULL',
            'fo.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            'tb.heading LIKE :textboxKeywordLike',
            'tb.body LIKE :textboxKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'textboxNow'         => Alaress_Date_Format::now()->getDatabase(),
            'textboxKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a matched textbox is determined by its updated_at time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':textboxNow',
            'tb.updated_at',
            7,
            56,
        );
    }

    /**
     * Textboxes are ordered by relevance, as they have a updated_at time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The textbox's title is its `heading`.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'tb.heading';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A textbox's type is always 'Textbox'.
     *
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return '"' . Hydrator::BASE_TYPE . '"';
    }

    /**
     * The textbox's ID is simply in its `textBoxID` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 'tb.textBoxID';
    }

    /**
     * A textbox's date is its updated_at date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 'tb.updated_at';
    }

    /**
     * Textboxes currently have no creator stored in the database, so return null.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return null;
    }

    /**
     * The textbox's folder is its `categoryID` column.
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return 'tb.categoryID';
    }

    /**
     * Textboxes are fetched from the `textBox` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM textBox tb
            INNER JOIN folder fo ON tb.categoryID = fo.id
        ';
    }
}
