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
        [$whereParts, $params] = $this->buildBaseWhereParams($keyword);

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
     * @param string $keyword
     * @return array
     */
    abstract protected function buildBaseWhereParams(string $keyword): array;

    /**
     * Build the expression for determining the relevance for an item in this
     * search.
     *
     * @return string
     */
    abstract protected function buildRelevanceExpression(): string;

    /**
     * Build the expression for determining the title for an item in this
     * search.
     *
     * @return string
     */
    abstract protected function buildTitleExpression(): string;

    /**
     * Build the expression for determining the type for an item in this
     * search.
     *
     * @return string
     */
    abstract protected function buildTypeExpression(): string;

    /**
     * Build the expression for determining the ID for an item in this
     * search.
     *
     * @return string
     */
    abstract protected function buildIdExpression(): string;

    /**
     * Build the expression for determining the date for an item in this
     * search.
     *
     * @return string
     */
    abstract protected function buildDateExpression(): string;

    /**
     * Build the expression for determining the creator for an item in this
     * search.
     *
     * @return string
     */
    abstract protected function buildCreatorExpression(): string;

    /**
     * Build the FROM part of the query, including any joins.
     *
     * @return string
     */
    abstract protected function buildFromStatement(): string;
}
