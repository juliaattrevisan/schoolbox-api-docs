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
use Schoolbox\IConfigLoader;
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Database\IQueryParamBuilder;
use Schoolbox\Search\Api\Database\QueryParams;
use Schoolbox\Search\Api\Filter\DataObject as FilterDataObject;

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
            'UNIX_TIMESTAMP(n.publish_at) < :now',
            'n.rejected_at IS NULL',
        ];
        $params = [
            'now'     => Alaress_Date_Format::now()->getUnixTimeStamp(),
            'keyword' => $keyword,
        ];

        // Apply keyword
        // TODO replace with match?
        $keywordSearchParts = [
            'n.title LIKE :keywordLike',
            'n.body LIKE :keywordLike',
            'n.blurb LIKE :keywordLike',
        ];
        $params['keywordLike'] = '%' . $this->quoter->escapeForLike($keyword) . '%';
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';


        // Apply filter
        // (This may happen outside of the UNION: unsure as of yet)
        // Some of these params may be generic to all searches: not sure yet
        if ($filter->types) {
            $whereParts[] = 'type IN ' . Builder::getArrayPlaceholders($filter->types, 'type');
            $params       = array_merge($params, Builder::getArrayParameters($filter->types, 'type'));
        }
        if ($filter->fromDate) {
            $whereParts[]       = 'UNIX_TIMESTAMP(n.publish_at) >= :fromDate';
            $params['fromDate'] = $filter->fromDate->getTimestamp();
        }
        if ($filter->toDate) {
            $whereParts[]     = 'UNIX_TIMESTAMP(n.publish_at) <= :toDate';
            $params['toDate'] = $filter->toDate->getTimestamp();
        }

        // Apply cursor
        // (Previous technique: < most relevant field, = most relevant and < next most relevant)
        // This can probably be made generic?
        // TODO consider reverting to previous, and moving to outside UNION
        if ($cursor) {
            $cursorSearchParts = [
                "(n.title > :title)",
                "(n.title = :title AND 'news' > :type)",
                "(n.title = :title AND 'news' = :type AND n.id > :id)",
            ];

            $whereParts[]        = '(' . implode(' OR ', $cursorSearchParts) . ')';
            $params['relevance'] = $cursor->relevance;
            $params['type']      = $cursor->type;
            $params['title']     = $cursor->title;
            $params['id']        = $cursor->id;
        }

        $where = implode(' AND ', $whereParts);

        // retrieve results
        // Relevance: 1 for articles published within 1 week of today,
        // sliding scale downward otherwise
        $query = "
            SELECT
                n.id,
                n.title AS title,
                'news' AS type,
                n.publish_at AS date,
                1 AS relevance
            FROM comms_news_article n
            WHERE $where
            ORDER BY
                n.title ASC,
                n.id ASC
            LIMIT :limit
        ";
        return new QueryParams($query, $params);
    }
}
