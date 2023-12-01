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
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            'fp.deleted="false"', // The type of forumPost.deleted is enum('true', 'false').
            'ft.deleted="false"', // The type of forumThread.deleted is enum('true', 'false').
        ];
        $keywordSearchParts = [
            "ft.title LIKE :postKeywordLike$paramKeySuffix",
            "MATCH(fp.body) AGAINST (:postKeyword$paramKeySuffix)",
        ];
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params = [
            "postNow$paramKeySuffix"         => Alaress_Date_Format::now()->getDatabase(),
            "postKeyword$paramKeySuffix"     => $keyword,
            "postKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a post is its modified time.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":postNow$paramKeySuffix",
            'modified',
            7,
            56,
        );
    }

    /**
     * Forum posts are ordered by relevance, as they have a modified time.
     *
     * @inheritdoc
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
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 'ft.title';
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
     * The post's ID is its `postID` column.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'postID';
    }

    /**
     * The post's date is its modified date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'modified';
    }

    /**
     * The post's creator is its `userID` column.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return 'userID';
    }

    /**
     * Forum posts do not have a folder.
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): ?string
    {
        return 'ft.categoryID';
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
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
        ';
    }
}
