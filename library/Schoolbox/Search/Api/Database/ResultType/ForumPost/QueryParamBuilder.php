<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\ForumPost;

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
     * * hidden posts _are_ fetched (as whether the viewer can view the post will be determined in the hydrator)
     * * posts whose body contains `$keyword` are fetched
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'p.deleted="false"', // The type of forumPost.deleted is enum('true', 'false').
        ];
        $keywordSearchParts = [
            'p.body LIKE :postKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'postNow'         => Alaress_Date_Format::now()->getDatabase(),
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
            'p.modified',
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
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'pt.title';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A forum post's type is always 'forumPost'.
     *
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return "'forumPost'";
    }

    /**
     * The post's ID is its `postID` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 'p.postID';
    }

    /**
     * The post's date is its modified date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 'p.modified';
    }

    /**
     * The post's creator is its `userID` column.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return 'p.userID';
    }

    /**
     * Forum posts do not have a title.
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return '""';
    }

    /**
     * Forum posts are fetched from the `forumPost` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM forumPost p
            INNER JOIN forumThread pt ON pt.threadID = p.threadID
        ';
    }
}
