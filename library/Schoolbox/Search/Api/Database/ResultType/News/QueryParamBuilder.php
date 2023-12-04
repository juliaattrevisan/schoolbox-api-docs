<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for a search for news.
 *
 * This may match an article on:
 * - title
 * - blurb
 * - body
 */
class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * Build the WHERE params such that:
     * * deleted articles are never fetched
     * * articles not yet accepted are never fetched
     * * rejected articles are never fetched
     * * articles whose publish date is not reached are never fetched
     * * articles whose title, description or blurb contain `$keyword` are
     *   fetched
     *
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            'n.deleted_at IS NULL',
            'n.accepted_at IS NOT NULL',
            "n.publish_at < :newsNow$paramKeySuffix",
            'n.rejected_at IS NULL',
        ];
        $params = [
            "newsNow$paramKeySuffix"     => Alaress_Date_Format::now()->getDatabase(),
            "newsKeyword$paramKeySuffix" => $keyword,
        ];

        // Apply keyword
        $keywordSearchParts = [
            "MATCH(n.title, n.body, n.blurb) AGAINST (:newsKeyword$paramKeySuffix)",
        ];
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';
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
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":newsNow$paramKeySuffix",
            'n.publish_at',
            7,
            56,
        );
    }

    /**
     * `buildRelevanceExpression` returns a dynamic value: therefore, news
     * results are ordered by relevance.
     *
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The title of a news result is simply the article's title.
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 'n.title';
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
     * The ID of a news result is simply the article's ID.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'n.id';
    }

    /**
     * The date of a news result is the article's publish date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'n.publish_at';
    }

    /**
     * The creator of a news result is the article's author.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return 'n.author_id';
    }

    /**
     * News results do not have folders, thus this returns `null`.
     *
     * (Whilst a news article may be audienced to a folder, news articles are
     * not displayed underneath a folder.)
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): ?string
    {
        return null;
    }

    /**
     * News results are simply fetched from the `comms_news_article` table:
     * currently, no joins are required.
     *
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return 'FROM comms_news_article n';
    }
}
