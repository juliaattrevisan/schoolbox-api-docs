<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for indexed searches.
 *
 * This relies on an index already having been built.
 */
class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * Floating-point to integer multiplier.
     * (Used when converting floating-point numbers to integers for comparison purposes)
     *
     * In order to avoid having to do comparisons on floating-point relevance scores, we convert floating-point
     * relevance scores to integers by first multiplying by FP_TO_INT_MULTIPLIER, and then rounding to the nearest integer.
     * Comparisons of floating-point numbers should be avoided for reasons given here:
     *  - https://floating-point-gui.de/errors/comparison/
     *
     * Comparisons (= <=, >=, etc.) are done on relevance scores in cursor-related calculations.
     * (see BaseQueryParamBuilder->buildMatchQueryParams()).
     *
     * Choosing a value for FP_TO_INT_MULTIPLIER
     *  - Floating-point relevance scores are the return values of MATCH...AGAINST operations.
     *  - MATCH...AGAINST operations typically return values between 0 and 10,000 for commonly searched document sizes.
     *    (The upper limit was found by: 1) taking a small passage of text, and doubling its size by copying and pasting
     *     many times until its size was 650kb, and its word count was over 100,000. 2) Running MATCH...AGAINST queries
     *     on some of the less-common words in the initial small passage of text. 3) Choosing the highest observed return
     *     value (8048.390625), and then just rounding up to 10,000.)
     *  - We want to convert floating point numbers in the range 0 to 10,000 to integers, while mostly preserving their
     *    sort order.
     *  - The range of the resulting integers should be representable by the standard MySQL INT data type.
     *  - If we choose FP_TO_INT_MULTIPLIER as 10,000, we get a maximum value of 100,000,000, which is well below
     *    MySQL INT max value (2,147,483,647).  So 10,000 will do.
     *    https://dev.mysql.com/doc/refman/8.0/en/integer-types.html
     *
     * Note we must use this FP_TO_INT_MULTIPLIER on all three relevance scores, to preserve their proportionality,
     * (so the effect of self::RELEVANCE_WEIGHTING_TITLE, etc. will be unchanged).
     */
    const FP_TO_INT_MULTIPLIER = 10000;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    protected function buildBaseWhereParams(
        string $keyword,
        string $paramKeySuffix = ''
    ): array {
        // Note: Do not trim the keyword.  Not trimming the keyword allows the user more flexibility when searching for
        // titles.  E.g.  A search for titles containing ' board ' excludes titles like 'headboard', 'surfboards', 'boards'.

        $strlenKeyword   = strlen($keyword);
        $keywordUnquoted = (
            ($strlenKeyword >= 2 && $keyword[0] === '"' && $keyword[$strlenKeyword - 1] === '"')
            ? substr($keyword, 1, $strlenKeyword - 2)
            : $keyword
        );

        return [
            "indexNow$paramKeySuffix"                        => Alaress_Date_Format::now()->getDatabase(),
            "indexKeyword$paramKeySuffix"                    => $keyword,
            "indexKeywordUnquoted$paramKeySuffix"            => $keywordUnquoted,
            "RELEVANCE_WEIGHTING_TITLE$paramKeySuffix"       => self::RELEVANCE_WEIGHTING_TITLE,
            "RELEVANCE_WEIGHTING_CONTENT$paramKeySuffix"     => self::RELEVANCE_WEIGHTING_CONTENT,
            "RELEVANCE_WEIGHTING_RECENT_DATE$paramKeySuffix" => self::RELEVANCE_WEIGHTING_RECENT_DATE,
            "FP_TO_INT_MULTIPLIER$paramKeySuffix"            => self::FP_TO_INT_MULTIPLIER,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function buildTitleRelevanceExpressionForWhereClause(string $keyword, string $paramKeySuffix = ''): string
    {
        // The algorithm used by MATCH...AGAINST to generate relevance scores is detailed here:
        //  * https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
        // The same algorithm for generating relevance scores is used for BOOLEAN MODE and NATURAL LANGUAGE MODE (this
        // has been confirmed by experiment).

        // This is a simplified version of the expression returned by buildTitleRelevanceExpression(), that only
        // includes computations that determine whether a row will be included in the search result.
        // The 'exact match on title' test is required so that searches for filenames such as 'shutterstock_282889763'
        // will return expected results.  MATCH...AGAINST alone will not work in that case because of the underscore.
        return (
            "IF(" .
                "si.title = :indexKeywordUnquoted$paramKeySuffix OR " .
                "MATCH (si.title) AGAINST (:indexKeyword$paramKeySuffix IN NATURAL LANGUAGE MODE) > 0," .
                "1, " .
                "0" .
            ")"
        );
    }

    /**
     * @inheritdoc
     */
    protected function buildTitleRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        // The algorithm used by MATCH...AGAINST to generate relevance scores is detailed here:
        //  * https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
        // The same algorithm for generating relevance scores is used for BOOLEAN MODE and NATURAL LANGUAGE MODE (this
        // has been confirmed by experiment).

        return (
            // Explanations of 'Bonus for exact match' and 'Bonus for short matching titles'
            // -----------------------------------------------------------------------------
            // A MATCH...AGAINST relevance score increases for every word within the keyword string that is found within
            // the searched string.  Example: the score generated by MATCH (title) AGAINST ('Cars and bikes'),
            // when title = 'Cars and bikes and random words that might include bike eventually', will be greater than
            // when title = 'Cars and bikes', even though 'Cars and bikes' is an exact match.
            // We want exact matches on title to be considered most relevant, hence the bonus for an exact match.
            // We want to counteract the tendency for abnormally long titles to rank higher than normally short matching
            // titles, hence the bonus for short matching titles.
            //    The bonus for short matching titles must be multiplied, not added, because we do not want to raise the
            // ranking of results that have zero match score on title (and were included only because they matched on
            // content).  Also, because the sort matching title bonus is multiplicative, the value to leave the score
            // unchanged is 1, not 0.

            "ROUND(:FP_TO_INT_MULTIPLIER$paramKeySuffix * (" .
                ":RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * (" .
                    "MATCH (si.title) AGAINST (:indexKeyword$paramKeySuffix IN NATURAL LANGUAGE MODE) + " .
                    "IF(si.title = :indexKeywordUnquoted$paramKeySuffix, 100, 0)" . // Bonus for exact match (see comment above).
                ") * " .
                "IF(LENGTH(si.title) <= 1.5 * LENGTH(:indexKeyword$paramKeySuffix), 1.1, 1)" . // Bonus for short matching titles (see comment above).
            "))"
        );
    }

    /**
     * @param string $keyword
     * @param string $paramKeySuffix
     * @return string
     */
    protected function buildContentRelevanceExpressionForWhereClause(string $keyword, string $paramKeySuffix = ''): string
    {
        // The algorithm used by MATCH...AGAINST to generate relevance scores is detailed here:
        //  * https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
        // The same algorithm for generating relevance scores is used for BOOLEAN MODE and NATURAL LANGUAGE MODE (this
        // has been confirmed by experiment).

        // This is a simplified version of the expression returned by buildContentRelevanceExpression(), that only
        // includes computations that determine whether a row will be included in the search result.
        return " MATCH (si.fulltext_index) AGAINST (:indexKeyword$paramKeySuffix IN NATURAL LANGUAGE MODE)";
    }

    /**
     * @inheritdoc
     */
    protected function buildContentRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        // The algorithm used by MATCH...AGAINST to generate relevance scores is detailed here:
        //  * https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
        // The same algorithm for generating relevance scores is used for BOOLEAN MODE and NATURAL LANGUAGE MODE (this
        // has been confirmed by experiment).

        return (
            "ROUND(:FP_TO_INT_MULTIPLIER$paramKeySuffix * (" .
                ":RELEVANCE_WEIGHTING_CONTENT$paramKeySuffix *" .
                " MATCH (si.fulltext_index) AGAINST (:indexKeyword$paramKeySuffix IN NATURAL LANGUAGE MODE)" .
            "))"
        );
    }

    /**
     * @inheritdoc
     */
    protected function buildDateRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        $datePart = $this->buildLinearDecayExpression(
            ":indexNow$paramKeySuffix",
            'si.date',
            7,
            56,
        );

        return (
            "ROUND(:FP_TO_INT_MULTIPLIER$paramKeySuffix * (" .
                ":RELEVANCE_WEIGHTING_RECENT_DATE$paramKeySuffix * IF(si.is_relevance_date_affected, $datePart, 1)" .
            "))"
        );
    }

    /**
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'si.date';
    }

    /**
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return "si.title";
    }

    /**
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return "si.created_by";
    }

    /**
     * This accepts all types, and rejects none
     */
    protected function getType(): string
    {
        return '';
    }

    /**
     * This builder may potentially handle any type or subtype.
     *
     * @param string $type
     * @return bool
     */
    protected function handlesType(string $type): bool
    {
        return true;
    }

    /**
     * This builder may potentially handle any type or subtype.
     *
     * @param string $type
     * @return bool
     */
    protected function handlesSubtype(string $type): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        return "si.type";
    }

    /**
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'si.identifier';
    }

    /**
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'si.folder_id';
    }

    /**
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return "FROM search_index si";
    }
}
