<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;

/**
 * Abstract base class for query param builders.
 */
abstract class BaseQueryParamBuilder implements IQueryParamBuilder
{
    /**
     * @inheritdoc
     */
    public function buildMatchQueryParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        int $limit,
    ): QueryParams {
        ['where' => $whereParts, 'params' => $params] = $this->buildBaseWhereParams($keyword);

        $relevancePart = $this->buildRelevanceExpression();
        $titlePart     = $this->buildTitleExpression();
        $typePart      = $this->buildTypeExpression();
        $idPart        = $this->buildIdExpression();
        $datePart      = $this->buildDateExpression();
        $creatorPart   = $this->buildCreatorExpression();

        // Apply filter
        if ($filter->types) {
            $whereParts[] = "$typePart IN " . Builder::getArrayPlaceholders($filter->types, 'userType');
            $params       = array_merge($params, Builder::getArrayParameters($filter->types, 'userType'));
        }
        if ($filter->fromDate) {
            $whereParts[]             = "$datePart >= :filterFromDate";
            $params['filterFromDate'] = $filter->fromDate->getDatabase();
        }
        if ($filter->toDate) {
            $whereParts[]           = "$datePart <= :filterToDate";
            $params['filterToDate'] = $filter->toDate->getDatabase();
        }
        if ($filter->creator) {
            $whereParts[]            = "$creatorPart = :filterCreator";
            $params['filterCreator'] = $filter->creator->getId();
        }

        // Apply cursor
        if ($cursor) {
            $cursorSearchParts = [
                "($relevancePart < :cursorRelevance)",
                "($relevancePart = :cursorRelevance AND $titlePart > :cursorTitle)",
                "($relevancePart = :cursorRelevance AND $titlePart = :cursorTitle AND $typePart > :cursorType)",
                "($relevancePart = :cursorRelevance AND $titlePart = :cursorTitle AND $typePart = :cursorType AND $idPart > :cursorId)",
            ];

            $whereParts[]              = '(' . implode(' OR ', $cursorSearchParts) . ')';
            $params['cursorRelevance'] = $cursor->relevance;
            $params['cursorType']      = $cursor->type;
            $params['cursorTitle']     = $cursor->title;
            $params['cursorId']        = $cursor->id;
        }

        $where = implode(' AND ', $whereParts);

        // Build query and params
        $from = $this->buildFromStatement();
        $query = "
            SELECT
                $idPart        AS id,
                $titlePart     AS title,
                $typePart      AS type,
                $datePart      AS date,
                $relevancePart AS relevance
            $from
            WHERE $where
            ORDER BY
                $relevancePart DESC,
                $titlePart ASC,
                $typePart ASC,
                $idPart ASC
            LIMIT :userLimit
        ";
        $params['userLimit'] = $limit;
        return new QueryParams($query, $params);
    }

    /**
     * Build the base set of WHERE parts and their params.
     *
     * @param string $keyword The search term from `buildMatchQueryParams`.
     * @return array{where: array<string>, params: array}
     */
    abstract protected function buildBaseWhereParams(string $keyword): array;

    /**
     * Build the expression for determining the relevance for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to a decimal number between 0 and 1: the greater the
     * number, the more relevant the result.
     *
     * Currently, the relevance of a result is not dependent on how closely it
     * matches the search term: thus, this doesn't take the `$keyword` from
     * `buildMatchQueryParams`.
     *
     * @return string An SQL expression for determining
     */
    abstract protected function buildRelevanceExpression(): string;

    /**
     * Build the expression for determining the title for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to a string value.
     *
     * @return string
     */
    abstract protected function buildTitleExpression(): string;

    /**
     * Build the expression for determining the type for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to a string value.
     *
     * @return string
     */
    abstract protected function buildTypeExpression(): string;

    /**
     * Build the expression for determining the ID for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to an integer value.
     *
     * @return string
     */
    abstract protected function buildIdExpression(): string;

    /**
     * Build the expression for determining the date for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to a database datetime value.
     *
     * @return string
     */
    abstract protected function buildDateExpression(): string;

    /**
     * Build the expression for determining the creator for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to an integer value, corresponding to the creator of
     * this item: if it has no known creator, 0 (rather than NULL) should be
     * returned (so that SQL comparisons work reliably).
     *
     * @return string
     */
    abstract protected function buildCreatorExpression(): string;

    /**
     * Build the FROM part of the query, including any joins; e.g.:
     * "FROM table t JOIN other_table ot ON ..."
     *
     * @return string
     */
    abstract protected function buildFromStatement(): string;
}
