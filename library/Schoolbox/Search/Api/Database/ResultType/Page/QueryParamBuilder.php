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
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts = [
            'f.deleted_at IS NULL',
            'f.name LIKE :pageKeywordLike OR fc.code LIKE :pageKeywordLike',
        ];
        $params = ['pageKeywordLike' => $this->quoter->escapeForLike($keyword)];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of pages is always maximum, so return a static value.
     */
    protected function buildRelevanceExpression(): string
    {
        return '1';
    }

    /**
     * Since `buildRelevanceExpression` returns a static value, page results are
     * not ordered by relevance.
     */
    protected function isOrderedByRelevance(): bool
    {
        return false;
    }

    /**
     * The title of a page result is always just its name.
     */
    protected function buildTitleExpression(): string
    {
        return "f.name";
    }

    /**
     * The type of a page result depends on what sort of page it is, in the
     * following order of preference:
     * * course: pages with a related course specification
     * * unit: pages with a related unit specification
     * * class: pages with a class code
     * * homepage: pages with no class code, but a homepage
     * * other: pages without a homepage
     */
    protected function buildTypeExpression(): string
    {
        // Just "page" for now though
        return "'page'";
    }

    /**
     * The ID of a page result will simply be the page's ID.
     */
    protected function buildIdExpression(): string
    {
        return 'f.id';
    }

    /**
     * Page results do not currently have dates: thus this will be null.
     */
    protected function buildDateExpression(): string
    {
        return 'null';
    }

    /**
     * Pages have no recorded creator: thus, this will always be 0.
     */
    protected function buildCreatorExpression(): string
    {
        return '0';
    }

    /**
     * Page results need to be fetched from the folder table: possibly joining
     * onto other tables to populate the result type and folder codes.
     */
    protected function buildFromStatement(): string
    {
        return "
            FROM folder f
            INNER JOIN folder_code fc ON f.id = fc.folder_id
        ";
    }
}
