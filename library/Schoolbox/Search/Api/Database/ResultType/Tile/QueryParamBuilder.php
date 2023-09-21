<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Tile;

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
     * * hidden tiles _are_ fetched (as whether the viewer can view the tile will be determined in the hydrator)
     * * tiles whose title_text or link contain `$keyword` are fetched
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            't.deleted_at IS NULL',
            'tl.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            // Tiles without a title should not appear in search results (AC for SA-8841).
            'TRIM(t.title_text)<>"" AND t.title_text LIKE :tileKeywordLike',
            't.link LIKE :tileKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'tileNow'         => Alaress_Date_Format::now()->getDatabase(),
            'tileKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a tile is its modified_at time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':tileNow',
            't.modified_at',
            7,
            56,
        );
    }

    /**
     * Tiles are ordered by relevance, as they have a modified time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The title of a tile is tile.title_text.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 't.title_text';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A tile's type is always 'tile'.
     *
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return "'tile'";
    }

    /**
     * The tile's ID is simply in its `id` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 't.id';
    }

    /**
     * The tile's date is its modified date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 't.modified_at';
    }

    /**
     * Tiles do not have a creator.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return null;
    }

    /**
     * The tile's folder is its tile_layout_for_folder.folder_id column.
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return 'tlf.folder_id';
    }

    /**
     * Tiles are fetched from the `tile` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM tile t
            INNER JOIN tile_layout tl ON tl.id=t.tile_layout_id
            INNER JOIN tile_layout_for_folder tlf ON tlf.tile_layout_id=tl.id
        ';
    }
}
