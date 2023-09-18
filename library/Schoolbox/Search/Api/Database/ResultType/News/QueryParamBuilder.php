<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for a search for news.
 *
 * This may match an article on:
 * - title
 * - blurb
 * - body
 *
 * @see \Schoolbox\Comms\News\Search\Result
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
     * * deleted articles are never fetched
     * * articles not yet accepted are never fetched
     * * rejected articles are never fetched
     * * articles whose publish date is not reached are never fetched
     * * articles whose title, description or blurb contain `$keyword` are
     *   fetched
     */

    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts = [
            'n.deleted_at IS NULL',
            'n.accepted_at IS NOT NULL',
            'n.publish_at < :newsNow',
            'n.rejected_at IS NULL',
        ];
        $params     = [
            'newsNow' => Alaress_Date_Format::now()->getDatabase(),
        ];

        // Apply keyword
        // TODO replacing with MATCH may return more accurate results?
        // (see https://mariadb.com/kb/en/full-text-index-overview/)
        // This match returns any results within strings, whether or not they
        // actually match a whole word.
        $keywordSearchParts        = [
            'n.title LIKE :newsKeywordLike',
            'n.body LIKE :newsKeywordLike',
            'n.blurb LIKE :newsKeywordLike',
        ];
        $params['newsKeywordLike'] = '%' . $this->quoter->escapeForLike($keyword) . '%';
        $whereParts[]              = '(' . implode(' OR ', $keywordSearchParts) . ')';
        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The news article's relevance:
     * * starts at its maximum value, and remains at that value for 7 days after
     *   publishing
     * * linearly decays until reaching a minimum of 0 at 8 weeks after
     *   publishing
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':newsNow',
            'n.publish_at',
            7,
            56,
        );
    }

    /**
     * `buildRelevanceExpression` returns a dynamic value: therefore, news
     * results are ordered by relevance.
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The title of a news result is simply the article's title.
     */
    protected function buildTitleExpression(): string
    {
        return 'n.title';
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
     * The ID of a news result is simply the article's ID.
     */
    protected function buildIdExpression(): string
    {
        return 'n.id';
    }

    /**
     * The date of a news result is the article's publish date.
     */
    protected function buildDateExpression(): ?string
    {
        return 'n.publish_at';
    }

    /**
     * The creator of a news result is the article's author.
     */
    protected function buildCreatorExpression(): ?string
    {
        return 'n.author_id';
    }

    /**
     * News results do not have folders, thus this returns `null`.
     *
     * (Whilst a news article may be audienced to a folder, news articles are
     * not displayed underneath a folder.)
     *
     * @return string|null
     */
    public function buildFolderExpression(): ?string
    {
        return null;
    }

    /**
     * News results are simply fetched from the `comms_news_article` table:
     * currently, no joins are required.
     */
    protected function buildFromStatement(): string
    {
        return 'FROM comms_news_article n';
    }
}
