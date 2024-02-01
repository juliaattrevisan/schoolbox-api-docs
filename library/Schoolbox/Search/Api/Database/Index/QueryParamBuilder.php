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
        $quoter = $this->quoter;

        // Remove all punctuation characters.
        $keywordMinusPunctuation = preg_replace("#[[:punct:]]#", "", $keyword);

        // Replace multiple spaces with single space.
        $keywordMinusPunctuation = preg_replace('!\s+!', ' ', $keywordMinusPunctuation);

        $params = [
            "indexNow$paramKeySuffix"                        => Alaress_Date_Format::now()->getDatabase(),
            "indexKeyword$paramKeySuffix"                    => $keyword,
            "indexKeywordLike$paramKeySuffix"                => '%' . $quoter->escapeForLike($keyword) . '%',
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
        $sqlSumTerms = [
            // NOTE: Must include punctuation for searches for filenames e.g. 'shutterstock_282889763'.
            "IF(si.title = :indexKeyword$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix, 0)",
            "IF(si.title = :indexKeywordMinusPunct$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.8, 0)",
            "IF(si.title LIKE :indexKeywordLike$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.6, 0)",
            "IF(si.title LIKE :indexKeywordMinusPunctLike$paramKeySuffix, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.4, 0)"
        ];

        foreach ($this->buildBaseWhereParams($keyword, $paramKeySuffix) as $key => $value) {
            if (str_starts_with($key, self::KEYWORD_TOKEN_LIKE_PREFIX)) {
                $sqlSumTerms[] = "IF(si.title LIKE :$key, :RELEVANCE_WEIGHTING_TITLE$paramKeySuffix * 0.2, 0)";
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
                MATCH (si.fulltext_index) AGAINST (:indexKeywordLike$paramKeySuffix IN NATURAL LANGUAGE MODE) /
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
