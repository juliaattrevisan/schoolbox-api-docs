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
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts         = [
            't.deleted_at IS NULL',
            'tl.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            // Tiles without a title should not appear in search results (AC for SA-8841).
            "TRIM(t.title_text)<>'' AND t.title_text LIKE :tileKeywordLike$paramKeySuffix",
            "TRIM(t.title_text)<>'' AND t.link LIKE :tileKeywordLike$paramKeySuffix",
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            "tileNow$paramKeySuffix"         => Alaress_Date_Format::now()->getDatabase(),
            "tileKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a tile is its modified_at time.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":tileNow$paramKeySuffix",
            't.modified_at',
            7,
            56,
        );
    }

    /**
     * Tiles are ordered by relevance, as they have a modified time.
     *
     * @inheritdoc
     */
    protected function isOrderedByRelevance(string $paramKeySuffix = ''): bool
    {
        return true;
    }

    /**
     * The title of a tile is tile.title_text.
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 't.title_text';
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
     * The tile's ID is simply in its `id` column.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 't.id';
    }

    /**
     * The tile's date is its modified date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 't.modified_at';
    }

    /**
     * Tiles do not have a creator.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): ?string
    {
        return null;
    }

    /**
     * The tile's folder is its tile_layout_for_folder.folder_id column.
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'tlf.folder_id';
    }

    /**
     * Tiles are fetched from the `tile` table.
     *
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM tile t
            INNER JOIN tile_layout tl ON tl.id=t.tile_layout_id
            INNER JOIN tile_layout_for_folder tlf ON tlf.tile_layout_id=tl.id
        ';
    }
}
