<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Page;

use Alaress\Database\Quoter\IQuoter;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for a search for pages.
 *
 * This may match a folder on:
 * * the page's name
 * * one of its extant class codes
 */
class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * Constructor
     *
     * @param IQuoter $quoter
     */
    public function __construct(
        private IQuoter $quoter
    ) {
    }

    /**
     * Build the WHERE params such that:
     * * deleted pages are never fetched
     * * pages whose name matches `$keyword` are fetched
     * * pages with a class code which matches `$keyword` are fetched
     *
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            'f.deleted_at IS NULL',
            "(f.name LIKE :pageKeywordLike$paramKeySuffix OR fc.code LIKE :pageKeywordLike$paramKeySuffix)",
        ];
        $params = ["pageKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%'];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of pages is always maximum, so return a static value.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return '1';
    }

    /**
     * Since `buildRelevanceExpression` returns a static value, page results are
     * not ordered by relevance.
     *
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return false;
    }

    /**
     * The title of a page result is always just its name.
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return "f.name";
    }

    /**
     * @inheritdoc
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A page's type depends on what sort of page it is, in the
     * following order of preference:
     * * course: pages with a related course specification
     * * unit: pages with a related unit specification
     * * class: pages with a class code
     * * group: pages with a user group
     * * homepage: pages with no class code, but a homepage
     * * folder: pages without a homepage
     *
     * @inheritdoc
     */
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        // NVL2: if param 1 is not null returns param 2, otherwise param 3 (https://mariadb.com/kb/en/nvl2/)
        return "CONCAT(
            '" . Hydrator::BASE_TYPE . ".',
            NVL2(
                cs.id,
                'course',
                NVL2(
                    cus.id,
                    'unit',
                    NVL2(
                        fc.code,
                        'class',
                        NVL2(
                            ug.cid,
                            'group',
                            NVL2(hc.template, 'homepage', 'folder')
                        )
                    )
                )
            )
        )";
    }

    /**
     * The ID of a page result will simply be the page's ID.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'f.id';
    }

    /**
     * A page's date is its updated_at date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'f.updated_at';
    }

    /**
     * Pages have no recorded creator: thus, this will be null.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): ?string
    {
        return 'f.created_by';
    }

    /**
     * Pages do not have folders, thus this returns `null`.
     *
     * (Whilst a page/folder is stored within another folder, pages are not
     * displayed underneath a folder.)
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): ?string
    {
        return null;
    }

    /**
     * Page results need to be fetched from the folder table: possibly joining
     * onto other tables to populate the result type and folder codes.
     *
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return "
            FROM folder f
            LEFT JOIN folder_code fc ON f.id = fc.folder_id AND fc.deleted_at IS NULL
            LEFT JOIN course_specification cs ON f.id = cs.folder_id AND cs.deleted_at IS NULL
            LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id AND cus.deleted_at IS NULL
            LEFT JOIN homepage_config hc ON f.id = hc.cid
            LEFT JOIN userGroups ug ON f.id = ug.cid
        ";
    }
}
