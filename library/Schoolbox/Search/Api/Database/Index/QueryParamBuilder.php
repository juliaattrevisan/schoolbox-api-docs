<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Quoter\IQuoter;
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
     * Prefix to use for SQL query parameter keys for tokens within the search keyword.
     *
     * E.g. If the search keyword is "Romeo and Juliet",
     *      the tokens within the search keyword are:
     *         {'Romeo', 'and', 'Juliet'}
     *      and the SQL query parameters could be used like this:
     *
     *      SELECT *
     *      FROM search_index
     *      WHERE (
     *          title LIKE :KEYWORD_TOKEN_LIKE_PREFIX_1 OR
     *          title LIKE :KEYWORD_TOKEN_LIKE_PREFIX_2 OR
     *          title LIKE :KEYWORD_TOKEN_LIKE_PREFIX_3
     *      )
     *
     *      Where self::INDEX_KEYWORD_WORD_PREFIX is 'INDEX_KEYWORD_WORD_'.
     */
    private const KEYWORD_TOKEN_LIKE_PREFIX = 'KEYWORD_TOKEN_LIKE_PREFIX_';

    /**
     * Constructor
     *
     * @param IQuoter $quoter
     */
    public function __construct(private IQuoter $quoter)
    {
    }

    /**
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $quoter          = $this->quoter;
        $strlenKeyword   = strlen($keyword);
        $keywordUnquoted = (
            ($strlenKeyword >= 2 && $keyword[0] === '"' && $keyword[$strlenKeyword - 1] === '"')
            ? substr($keyword, 1, $strlenKeyword - 2)
            : $keyword
        );

        // Remove all punctuation characters and replace multiple spaces with single space.
        // (see comment 'Why strip punctuation?' below)
        $keywordMinusPunctuation = preg_replace("#[[:punct:]]#", "", $keywordUnquoted);
        $keywordMinusPunctuation = preg_replace('!\s+!', ' ', $keywordMinusPunctuation);

        $params = [
            "indexNow$paramKeySuffix"                        => Alaress_Date_Format::now()->getDatabase(),
            "indexKeyword$paramKeySuffix"                    => $keyword,
            "indexKeywordUnquoted$paramKeySuffix"            => $keywordUnquoted,
            "indexKeywordUnquotedLike$paramKeySuffix"        => '%' . $quoter->escapeForLike($keywordUnquoted) . '%',
            "indexKeywordMinusPunct$paramKeySuffix"          => $keywordMinusPunctuation,
            "indexKeywordMinusPunctLike$paramKeySuffix"      => '%' . $quoter->escapeForLike($keywordMinusPunctuation) . '%',
            "RELEVANCE_WEIGHTING_TITLE$paramKeySuffix"       => self::RELEVANCE_WEIGHTING_TITLE,
            "RELEVANCE_WEIGHTING_CONTENT$paramKeySuffix"     => self::RELEVANCE_WEIGHTING_CONTENT,
            "RELEVANCE_WEIGHTING_RECENT_DATE$paramKeySuffix" => self::RELEVANCE_WEIGHTING_RECENT_DATE,
        ];

        $keywordWords = explode(' ', $keywordMinusPunctuation);

        foreach ($keywordWords as $i => $word) {
            if (strlen($word) > 1) {
                $params[self::KEYWORD_TOKEN_LIKE_PREFIX . "$i$paramKeySuffix"] = '%' . $quoter->escapeForLike(trim($word)) . '%';
            }
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function buildTitleRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        $keyword = trim($keyword);

        // Why strip punctuation?
        // ----------------------
        // MATCH...AGAINST on the full-text index disregards punctuation.  We want the behaviour of the LIKE searches on
        // title to be as consistent as is practical with the behaviour of the MATCH...AGAINST searches on content, so we
        // strip punctuation characters from the title before applying the LIKE conditions.
        // However, in some cases we do need to consider punctuation characters - e.g. when searching for a filename
        // containing underscores.  So we need LIKE conditions that include punctuation also.
        // With both sets of LIKE conditions in place, matches on title will work similarly to matches on content if
        // the user does not include punctuation, but also punctuation will be considered for matches on title only.
        // This will result in less confusing behaviour than if we didn't have the stripped-punctuation checks.
        //
        // Examples:
        //  * Search for 'one, two' (without double-quotes).
        //    Results should include:
        //     * all items whose title contains 'one', (requires stripped-punctuation condition).
        //     * all items whose title contains 'two',
        //  * Search for '"one, two"' (with double-quotes).
        //    Results should include:
        //     * all items whose title contains 'one two', (requires stripped-punctuation condition).
        //     * all items whose title contains 'one, two' (requires non-stripped-punctuation condition),
        //
        // We want the above-described behaviour because that is how MATCH...AGAINST behaves, and because consistency is good.

        $sqlSumTerms = [
            "IF(si.title = :indexKeyword$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix, 0)",
            "IF(si.title LIKE :indexKeywordUnquotedLike$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.6, 0)",
            "IF(
                 (:indexKeywordMinusPunct$paramKeySuffix <> :indexKeyword$paramKeySuffix),
                 IF(si.title = :indexKeywordMinusPunct$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.8, 0) +
                 IF(si.title LIKE :indexKeywordMinusPunctLike$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.4, 0),
                 0
             )"
        ];

        // If the keyword has not been surrounded by double-quotes...
        if ($keyword[0] !== '"' || $keyword[strlen($keyword) - 1] !== '"') {
            // Search for each word individually to mimic the behaviour of MATCH...AGAINST
            // (MATCH...AGAINST is used in buildContentRelevanceExpression()).
            foreach ($this->buildBaseWhereParams($keyword, $paramKeySuffix) as $key => $value) {
                if (str_starts_with($key, self::KEYWORD_TOKEN_LIKE_PREFIX)) {
                    $sqlSumTerms[] = "IF(si.title LIKE :$key, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.2, 0)";
                }
            }
        }

        return '(' . implode(' + ', $sqlSumTerms) . ')';
    }

    /**
     * @inheritdoc
     */
    protected function buildContentRelevanceExpression(string $keyword, string $paramKeySuffix = ''): string
    {
        // The algorithm used by MATCH...AGAINST to generate relevance scores is detailed here:
        //  * https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html
        // The same algorithm is used for BOOLEAN MODE and NATURAL LANGUAGE MODE.  I confirmed this by experiment.
        //
        // Dividing by the maxContentRelevance results in a range for contentRelevance between 0 and 1.
        // The contentRelevance (between 0 and 1) is then multiplied by the RELEVANCE_WEIGHTING_CONTENT,
        // which results in a range of [0, self::RELEVANCE_WEIGHTING_CONTENT].
        return (
            ":RELEVANCE_WEIGHTING_CONTENT$paramKeySuffix * " .
            "(
                MATCH (si.fulltext_index) AGAINST (:indexKeywordUnquotedLike$paramKeySuffix IN NATURAL LANGUAGE MODE) /
                maxContentRelevanceTable.maxContentRelevance
             )"
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

        return ":RELEVANCE_WEIGHTING_RECENT_DATE$paramKeySuffix * IF(si.is_relevance_date_affected, $datePart, 1)";
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
        return (
            "FROM search_index si
             JOIN (
                 SELECT
                 MAX(MATCH (si2.fulltext_index) AGAINST (:indexKeyword$paramKeySuffix IN NATURAL LANGUAGE MODE)) AS maxContentRelevance
                 FROM search_index AS si2
             ) AS maxContentRelevanceTable ON (TRUE)"
        );
    }
}
