<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Database\IQueryParamBuilder;
use Schoolbox\Search\Api\Database\QueryParams;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;

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
class QueryParamBuilder implements IQueryParamBuilder
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
     * @inheritdoc
     */
    public function buildMatchQueryParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        int $limit,
    ): QueryParams {
        // Don't show deleted news
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
        // TODO replace with match?
        $keywordSearchParts        = [
            'n.title LIKE :newsKeywordLike',
            'n.body LIKE :newsKeywordLike',
            'n.blurb LIKE :newsKeywordLike',
        ];
        $params['newsKeywordLike'] = '%' . $this->quoter->escapeForLike($keyword) . '%';
        $whereParts[]              = '(' . implode(' OR ', $keywordSearchParts) . ')';


        // Apply filter
        // (This may happen outside of the UNION: unsure as of yet)
        // Some of these params may be generic to all searches: not sure yet
        if ($filter->types) {
            $whereParts[] = 'type IN ' . Builder::getArrayPlaceholders($filter->types, 'newsType');
            $params       = array_merge($params, Builder::getArrayParameters($filter->types, 'newsType'));
        }
        if ($filter->fromDate) {
            $whereParts[]           = 'n.publish_at >= :newsFromDate';
            $params['newsFromDate'] = $filter->fromDate->getDatabase();
        }
        if ($filter->toDate) {
            $whereParts[]         = 'n.publish_at <= :newsToDate';
            $params['newsToDate'] = $filter->toDate->getDatabase();
        }

        // Relevance explanation:
        // * if an article's been published no more than 7 days ago, it is
        //   maximally relevant
        // * otherwise, its relevance diminishes linearly to 0.1 at 8 weeks
        //   old
        $relevancePart = 'CAST(
            LEAST(
                1,
                GREATEST(
                    1 - ((DATEDIFF(:newsNow, n.publish_at) + 7) / 63),
                    0.1
                )
            )
            AS DECIMAL(2,2)
        )';
        $titlePart     = 'n.title';
        $typePart      = "'news'";
        $idPart        = 'n.id';

        // Apply cursor
        // (Previous technique: < most relevant field, = most relevant and < next most relevant)
        // This can probably be made generic?
        // TODO consider reverting to previous, and moving to outside UNION
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

        // retrieve results
        $query = "
            SELECT
                n.id,
                n.title AS title,
                'news' AS type,
                n.publish_at AS date,
                $relevancePart AS relevance
            FROM comms_news_article n
            WHERE $where
            ORDER BY
                relevance DESC,
                n.title ASC,
                n.id ASC
            LIMIT :newsLimit
        ";
        $params['newsLimit'] = $limit;
        return new QueryParams($query, $params);
    }
}
