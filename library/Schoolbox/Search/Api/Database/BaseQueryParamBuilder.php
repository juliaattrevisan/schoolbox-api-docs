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
    ): ?QueryParams {
        ['where' => $whereParts, 'params' => $params] = $this->buildBaseWhereParams($keyword);

        $relevancePart = $this->buildRelevanceExpression();
        $titlePart     = $this->buildTitleExpression();
        $typePart      = $this->buildTypeExpression();
        $idPart        = $this->buildIdExpression();
        $datePart      = $this->buildDateExpression();
        $creatorPart   = $this->buildCreatorExpression();
        $folderPart    = $this->buildFolderExpression();
        $havingParts   = [];

        // Apply filter
        if ($filter->types) {
            $subTypes = array_filter(
                $filter->types,
                function (string $type) {
                    return str_starts_with($type, $this->getType() . '.');
                }
            );
            if (!empty($subTypes)) {
                $bindings = Builder::getBindings($subTypes, $this->getType());

                // Why introduce a HAVING clause?
                // ------------------------------
                // Variable `type` is the result of an expression, and so will not be available in the WHERE clause.
                // It will be available in the HAVING clause however, so let's put it there instead.
                // (Note `type` comes from the SELECT clause, defined in this function below).
                // We ought to be able to use the expression itself in the WHERE clause, like this:
                //    Example: $whereParts = "$typePart IN ({$bindings->getPlaceholder()})";
                // But that does not produce expected results for unknown reasons.  So we introduce a HAVING clause.
                // @see https://github.com/alaress/schoolbox/pull/28994
                $havingParts[] = "`type` IN ({$bindings->getPlaceholder()})";

                $params = array_merge($params, $bindings->getParams());
            } elseif (!in_array($this->getType(), $filter->types)) {
                // there are no relevant types in this filter
                return null;
            }
        }
        if ($filter->fromDate) {
            // No date part? Exclude this type of result entirely
            if (isset($datePart)) {
                $whereParts[]             = "$datePart >= :filterFromDate";
                $params['filterFromDate'] = $filter->fromDate->getDatabase();
            } else {
                return null;
            }
        }
        if ($filter->toDate) {
            // No date part? Exclude this type of result entirely
            if (isset($datePart)) {
                $whereParts[]           = "$datePart <= :filterToDate";
                $params['filterToDate'] = $filter->toDate->getDatabase();
            } else {
                return null;
            }
        }
        if ($filter->creator) {
            // No creator part? Exclude this type of result entirely
            if (isset($creatorPart)) {
                $whereParts[]            = "$creatorPart = :filterCreator";
                $params['filterCreator'] = $filter->creator->getId();
            } else {
                return null;
            }
        }
        if ($filter->folder) {
            // No folder part? Exclude this type of result entirely
            if (isset($folderPart)) {
                $whereParts[]           = "$folderPart = :filterFolder";
                $params['filterFolder'] = $filter->folder->getId();
            } else {
                return null;
            }
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

        // Build query and params
        $from = $this->buildFromStatement();
        $query = (
            "SELECT DISTINCT
                $idPart        AS id,
                $titlePart     AS title,
                $typePart      AS type,
                $relevancePart AS relevance
            $from" .
            ((count($whereParts) > 0) ? "\nWHERE " . implode(' AND ', $whereParts) : '') .
            ((count($havingParts) > 0) ? "\nHAVING " . implode(' AND ', $havingParts) : '')
        );

        // Build order parts
        $orderParts = [];
        if ($this->isOrderedByRelevance()) {
            $orderParts[] = "$relevancePart DESC";
        }
        $orderParts[] = "$titlePart ASC";
        $orderParts[] = "`type` ASC";
        $orderParts[] = "$idPart ASC";
        $query .= ' ORDER BY ' . implode(', ', $orderParts);
        $query .= ' LIMIT :limit';

        $params['limit'] = $limit;
        return new QueryParams($query, $params);
    }

    /**
     * Build an expression whose result:
     * * is between `$min` and `$max`
     * * is `$max` if `$dateExpressionA` and `$dateExpressionB` are within
     *   `$daysAtMax` of one another
     * * is 0 if `$dateExpressionA` and `$dateExpressionB` are more than
     *   `$daysAtMin` apart
     * * otherwise, decays linearly from `$max` to 0 the further apart they are
     *
     * Used for building relevance expressions which depend on how far in the
     * past or future a date is.
     *
     * @param string $dateExpressionA Expression for the first date to compare.
     * @param string $dateExpressionB Expression for the second date to compare.
     * @param int    $daysAtMax       The maximum number of days which the two
     *                                date expressions may be apart, in order
     *                                for `$max` to be the returned value.
     * @param int    $daysAtMin       The minimum number of days which the two
     *                                date expressions may be apart, in order
     *                                for `$min` to be the returned value.
     * @param float  $max             The max value of the expression: defaults
     *                                to 1.
     * @return string
     */
    protected function buildLinearDecayExpression(
        string $dateExpressionA,
        string $dateExpressionB,
        int $daysAtMax,
        int $daysAtMin,
        float $max = 1,
    ): string {
        $absDateDiff = "ABS(DATEDIFF($dateExpressionA, $dateExpressionB))";

        // Derivation of line equation
        // ---------------------------
        // The y-axis in the diagram below is the $relevance, and the x-axis is the $absDateDiff.
        // The line is denoted by the asterisks.
        //
        // The line equation is:
        $relevanceUnrestrictedSql = "-($max / ($daysAtMin - $daysAtMax)) * ($absDateDiff - $daysAtMax) + $max";
        //
        // Compare the line equation above to the diagram below, and note:
        //  * Gradient = (rise / run) = -($max / ($daysAtMin - $daysAtMax))
        //  * $relevance=$max when $absDateDiff=$daysAtMax
        //  * $relevance=0    when $absDateDiff=$daysAtMin
        //
        //   $relevance
        //        ^   *
        //        |      *
        //        |         *
        //        |            *
        // $max --+ - - - - - - - *
        //        |               |  *
        //        |               |     *
        //        |               |        *
        //        |               |           *
        //        |               |              *
        //        |               |                 *
        // <------+---------------+--------------------*---------------> $absDateDiff
        //        |               |                    |  *
        //        v               $daysAtMax           $daysAtMin

        // Use LEAST() and GREATEST() to restrict the maximum value of $relevance to $max, and the minimum value to 0.
        return "LEAST($max, GREATEST($relevanceUnrestrictedSql, 0))";

        // Note regarding testing
        // ----------------------
        // The SQL used above can be tested by getting the generated SQL, substituting a range of values for
        // $absDateDiff, and then copying and pasting to a MySQL command-line.  Like so:
        // SELECT LEAST(1, GREATEST(-(1 / (56 - 7)) * (0 - 7) + 1, 0)) AS relevance;
        // SELECT LEAST(1, GREATEST(-(1 / (56 - 7)) * (1 - 7) + 1, 0)) AS relevance;
        // SELECT LEAST(1, GREATEST(-(1 / (56 - 7)) * (2 - 7) + 1, 0)) AS relevance;
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
     * Does the relevance of an item depend on the evaluation of an expression?
     *
     * Required because `ORDER BY` on a static expression causes later
     * conditions in the `ORDER BY` to be ignored.
     *
     * @return bool true if `buildRelevanceExpression` is a non-static value:
     *              false if static.
     */
    abstract protected function isOrderedByRelevance(): bool;

    /**
     * Build the expression for determining the title for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to a string value.
     *
     * @return string
     */
    abstract protected function buildTitleExpression(): string;

    abstract protected function getType(): string;

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
     * @return string|null The expression, or `null` if this item type does not
     *                     have a date.
     *                     (Items without dates will never match if there is a
     *                     date filter on the search.)
     */
    abstract protected function buildDateExpression(): ?string;

    /**
     * Build the expression for determining the creator for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to an integer value, corresponding to the creator of
     * this item.
     *
     * @return string|null The expression, or `null` if this item type does not
     *                     have a creator.
     *                     (Items without creators will never match if there is
     *                     a creator filter on the search.)
     */
    abstract protected function buildCreatorExpression(): ?string;

    /**
     * Build the expression for determining the folder for an item in this
     * search, as used in the `SELECT` and `WHERE` parts of the query.
     *
     * This should evaluate to an integer value, corresponding to the folder the
     * item belongs in.
     *
     * @return string|null The expression, or `null` if this item type does not
     *                     have a folder.
     *                     (Items without folder will never match if there is a
     *                     folder filter on the search.)
     */
    abstract protected function buildFolderExpression(): ?string;

    /**
     * Build the FROM part of the query, including any joins; e.g.:
     * "FROM table t JOIN other_table ot ON ..."
     *
     * @return string
     */
    abstract protected function buildFromStatement(): string;
}
