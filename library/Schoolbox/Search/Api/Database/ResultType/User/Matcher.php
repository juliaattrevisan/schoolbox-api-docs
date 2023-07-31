<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\IQuoter;
use Schoolbox\IConfigLoader;
use Schoolbox\Search\Api\Cursor\DataObject as Cursor;
use Schoolbox\Search\Api\Database\IMatcher;
use Schoolbox\Search\Api\Database\QueryParams;
use Schoolbox\Search\Api\Filter\DataObject as Filter;

/**
 * Search for a user which matches a particular keyword and filter.
 *
 * This may match a user on:
 * * their first, preferred, or last name
 * * their username
 * * their external ID
 */
class Matcher implements IMatcher
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param IQuoter       $quoter
     */
    public function __construct(
        private IConfigLoader $config,
        private IQuoter $quoter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function buildMatchQueryParams(string $keyword, Filter $filter, ?Cursor $cursor, int $limit): QueryParams
    {
        // Unsure if exact search should be used here?
//        // test for exact search, and if so return those results instead
//        if (preg_match('#".+"#', $keyword)) {
//            // strip the prepended and appended quote characters
//            $keyword = substr($keyword, 1, strlen($keyword) - 2);
//            return $this->searchExact($keyword, $offset, $limit, $count);
//        }


        // Don't show deleted users
        $whereParts = ['u.deleted_at IS NULL'];

        // Show disabled users only if ticked (hide when unticked)
        if (!$this->config->get('show_disabled_users')) {
            $whereParts[] = 'u.enabled = 1';
        }

        // Don't allow LIKE searches on keywords < 2 chars long
        // Probably shouldn't allow any searches on < 2 chars, but shrug
        // TODO consider injecting predicate builder or database itself
        $keywordSearchParts = ['u.synergy_id = :keyword'];
        $params             = ['keyword' => $keyword];
        if (mb_strlen($keyword, 'UTF-8') >= 2) {
            $keywordSearchParts[] = "u.username LIKE :keywordLike";
            $keywordSearchParts[] = "u.firstname LIKE :keywordLike";
            $keywordSearchParts[] = "u.preferred_name LIKE :keywordLike";
            $keywordSearchParts[] = "u.lastname LIKE :keywordLike";
            $keywordSearchParts[] = "CONCAT_WS(' ', u.title, u.firstname, u.lastname) LIKE :keywordLike";
            $keywordSearchParts[] = "CONCAT_WS(' ', u.firstname, u.lastname) LIKE :keywordLike";
            $keywordSearchParts[] = "CONCAT_WS(' ', u.title, u.preferred_name, u.lastname) LIKE :keywordLike";
            $keywordSearchParts[] = "CONCAT_WS(' ', u.preferred_name, u.lastname) LIKE :keywordLike";
            $keywordSearchParts[] = "CONCAT_WS(' ', u.title, u.lastname) LIKE :keywordLike";
            $params['keywordLike'] = '%' . $this->quoter->escapeForLike($keyword) . '%';
        }
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';

        // Apply filter
        // (This may happen outside of the UNION: unsure as of yet)
        // Some of these params may be generic to all searches: not sure yet
        if ($filter->types) {
            $whereParts[] = 'type IN ' . Builder::getArrayPlaceholders($filter->types, 'type');
            $params       = array_merge($params, Builder::getArrayParameters($filter->types, 'type'));
        }
        if ($filter->fromDate) {
            $whereParts[]       = 'UNIX_TIMESTAMP(u.created_at) >= :fromDate';
            $params['fromDate'] = $filter->fromDate->getTimestamp();
        }
        if ($filter->toDate) {
            $whereParts[]     = 'UNIX_TIMESTAMP(u.created_at) <= :toDate';
            $params['toDate'] = $filter->toDate->getTimestamp();
        }

        // Apply cursor
        // (Previous technique: < most relevant field, = most relevant and < next most relevant)
        // This can probably be made generic?
        if ($cursor) {
            $cursorSearchParts = [
                'relevance < :relevance',
                '(relevance = :relevance AND title > :title)',
                '(relevance = :relevance AND title = :title AND type > :type)',
                '(relevance = :relevance AND title = :title AND type = :type AND id > :id)',
            ];

            $whereParts[]        = '(' . implode(' OR ', $cursorSearchParts) . ')';
            $params['relevance'] = $cursor->relevance;
            $params['type']      = $cursor->type;
            $params['title']     = $cursor->title;
            $params['id']        = $cursor->id;
        }

        $where = implode(' AND ', $whereParts);

        // retrieve results
        // Finagle a title together from chosen + last name for cursoring only:
        // this may be presented differently on the frontend
        $query = "
            SELECT
                u.id,
                CONCAT(u.chosen_name, ' ', u.lastname) AS title,
                CONCAT('user.', r.group_type) AS type,
                u.updated_at AS date,
                1 AS relevance
            FROM `user` u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
            WHERE $where
        ";
        return new QueryParams($query, $params);
    }
}
