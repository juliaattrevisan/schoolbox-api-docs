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
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;
use Schoolbox\Search\Api\Database\IQueryParamBuilder;
use Schoolbox\Search\Api\Database\QueryParams;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;

/**
 * Query parameter builder for a search for users.
 *
 * This may match a user on:
 * * their first, preferred, or last name
 * * their username
 * * their external ID
 */
class QueryParamBuilder extends BaseQueryParamBuilder
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

    protected function buildBaseWhereParams(string $keyword): array
    {
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

        return [
            $whereParts,
            $params,
        ];
    }

    protected function buildRelevanceExpression(): string
    {
        return '1';
    }

    protected function buildTitleExpression(): string
    {
        return "CONCAT(u.chosen_name, ' ', u.lastname)";
    }

    protected function buildTypeExpression(): string
    {
        return "CONCAT('user.', r.group_type)";
    }

    protected function buildIdExpression(): string
    {
        return 'u.id';
    }

    protected function buildDateExpression(): string
    {
        return 'u.updated_at';
    }

    protected function buildCreatorExpression(): string
    {
        return 'NULL';
    }

    protected function buildFromStatement(): string
    {
        return "
            FROM `user` u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
        ";
    }
}
