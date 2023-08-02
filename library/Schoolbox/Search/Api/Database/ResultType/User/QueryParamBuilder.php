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
use Schoolbox\Search\Api\Cursor\DataObject as CursorDataObject;
use Schoolbox\Search\Api\Database\IQueryParamBuilder;
use Schoolbox\Search\Api\Database\QueryParams;
use Schoolbox\Search\Api\Filter\DataObject as FilterDataObject;

/**
 * Query parameter builder for a search for users.
 *
 * This may match a user on:
 * * their first, preferred, or last name
 * * their username
 * * their external ID
 */
class QueryParamBuilder implements IQueryParamBuilder
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
    public function buildMatchQueryParams(
        string $keyword,
        FilterDataObject $filter,
        ?CursorDataObject $cursor,
        int $limit,
    ): QueryParams {
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
        $keywordSearchParts = ['u.synergy_id = :userKeyword'];
        $params             = ['userKeyword' => $keyword];
        if (mb_strlen($keyword, 'UTF-8') >= 2) {
            $keywordSearchParts[]      = "u.username LIKE :userKeywordLike";
            $keywordSearchParts[]      = "u.firstname LIKE :userKeywordLike";
            $keywordSearchParts[]      = "u.preferred_name LIKE :userKeywordLike";
            $keywordSearchParts[]      = "u.lastname LIKE :userKeywordLike";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.title, u.firstname, u.lastname) LIKE :userKeywordLike";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.firstname, u.lastname) LIKE :userKeywordLike";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.title, u.preferred_name, u.lastname) LIKE :userKeywordLike";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.preferred_name, u.lastname) LIKE :userKeywordLike";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.title, u.lastname) LIKE :userKeywordLike";
            $params['userKeywordLike'] = '%' . $this->quoter->escapeForLike($keyword) . '%';
        }
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';

        // Apply filter
        // (This may happen outside of the UNION: unsure as of yet)
        // Some of these params may be generic to all searches: not sure yet
        if ($filter->types) {
            $whereParts[] = 'type IN ' . Builder::getArrayPlaceholders($filter->types, 'userType');
            $params       = array_merge($params, Builder::getArrayParameters($filter->types, 'userType'));
        }
        if ($filter->fromDate) {
            $whereParts[]           = 'u.created_at >= :userFromDate';
            $params['userFromDate'] = $filter->fromDate->getDatabase();
        }
        if ($filter->toDate) {
            $whereParts[]         = 'u.created_at <= :userToDate';
            $params['userToDate'] = $filter->toDate->getDatabase();
        }

        // Apply cursor
        // (Previous technique: < most relevant field, = most relevant and < next most relevant)
        // This can probably be made generic?
        // TODO consider reverting to previous, and moving to outside UNION
        // TODO relevance/type/title/id parts can be made generic
        $relevancePart = '1';
        $titlePart     = "CONCAT(u.chosen_name, ' ', u.lastname)";
        $typePart      = "CONCAT('user.', r.group_type)";
        $idPart        = 'u.id';
        if ($cursor) {
            $cursorSearchParts = [
                "($relevancePart < :cursorRelevance)",
                "($relevancePart = :cursorRelevance AND $titlePart > :cursorTitle)",
                "($relevancePart = :cursorRelevance AND $titlePart = :cursorTitle AND $typePart > :cursorType)",
                "($relevancePart = :cursorRelevance AND $titlePart = :cursorTitle AND $typePart = :cursorType AND u.id > $idPart)",
            ];

            $whereParts[]          = '(' . implode(' OR ', $cursorSearchParts) . ')';
            $params['cursorType']  = $cursor->type;
            $params['cursorTitle'] = $cursor->title;
            $params['cursorId']    = $cursor->id;
        }

        $where = implode(' AND ', $whereParts);

        // retrieve results
        // Finagle a title together from chosen + last name for cursoring only:
        // this may be presented differently on the frontend
        $query = "
            SELECT
                $idPart AS id,
                $titlePart AS title,
                $typePart AS type,
                u.updated_at AS date,
                $relevancePart AS relevance
            FROM `user` u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
            WHERE $where
            ORDER BY
                $titlePart ASC,
                $typePart ASC,
                u.id ASC
            LIMIT :userLimit
        ";
        $params['userLimit'] = $limit;
        return new QueryParams($query, $params);
    }
}
