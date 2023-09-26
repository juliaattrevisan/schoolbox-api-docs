<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for forum post searches.
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
     * * deleted posts are never fetched
     * * the search returns:
     *    * each thread whose title contains the keyword, or among whose posts is at least one post that contains the keyword.
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'fp.deleted="false"', // The type of forumPost.deleted is enum('true', 'false').
            'ft.deleted="false"', // The type of forumThread.deleted is enum('true', 'false').
        ];
        $keywordSearchParts = [
            'ft.title LIKE :postKeywordLike',
            'fp.body LIKE :postKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'postNow'         => Alaress_Date_Format::now()->getDatabase(),
            'postKeyword'     => $this->quoter->escapeForLike($keyword),
            'postKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a post is its modified time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':postNow',
            $this->getSubqueryForMostRelevantPostInThread('modified'),
            7,
            56,
        );
    }

    /**
     * Forum posts are ordered by relevance, as they have a modified time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * Forum posts do not have a title, but the linked thread does have a title.
     * Return the thread title because if a thread title contains the keyword, the thread should be included in the
     * search result.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'ft.title';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return '"' . Hydrator::BASE_TYPE . '"';
    }

    /**
     * The post's ID is its `postID` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return $this->getSubqueryForMostRelevantPostInThread('postID');
    }

    /**
     * The post's date is its modified date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return $this->getSubqueryForMostRelevantPostInThread('modified');
    }

    /**
     * The post's creator is its `userID` column.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return $this->getSubqueryForMostRelevantPostInThread('userID');
    }

    /**
     * Forum posts do not have a folder.
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return '""';
    }

    /**
     * The object to return for forum search results is the post that is the most appropriate post for a given matching
     * thread.
     *
     * A thread matches the search if:
     *  * the thread title contains the keyword OR
     *  * any post within the thread contains the keyword.
     *
     * The most appropriate post for any matching thread is:
     *  * the most recent post that contains the keyword, or
     *  * the most recent post (if no post contains the keyword)
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
        ';
    }

    /**
     * Note regarding performance
     * --------------------------
     * Creating this subquery for each of buildIdExpression(), buildDateExpression(), and buildCreatorExpression() will
     * result in a near-identical subquery repeated multiple times in the query that is eventually run.
     * A way to remove the repeated subqueries is demonstrated in https://github.com/alaress/schoolbox/pull/28981.
     * The current method is good enough for now.
     *
     * @param string $columnToSelect
     * @return string
     */
    private function getSubqueryForMostRelevantPostInThread(string $columnToSelect): string
    {
        return (
            // Select from the most recent post in the thread whose body contains the keyword, otherwise the most recent post.
            '(
                SELECT ' . $this->quoter->quoteIdentifier($columnToSelect) . '
                FROM  forumPost AS fp2
                WHERE fp2.threadID=ft.threadID
                ORDER BY LOCATE(:postKeyword, fp2.body)>0 DESC, fp2.modified DESC, fp2.postID ASC
                LIMIT 1
             )'
            // NOTE: Order by "fp2.postID ASC" above is necessary so that consistent results are returned each time
            //       the query runs given that fp2.modified may not be unique.
        );
    }
}
