<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Alaress\Database\Quoter\IQuoter;
use Schoolbox\IConfigLoader;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

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

    /**
     * Build the WHERE params such that:
     * * deleted users are never fetched
     * * disabled users are never fetched if `config.show_disabled_users` is off
     * * users whose external ID matches `$keyword` are fetched
     * * users whose title + (first name or preferred name) + last name is like
     *   `$keyword` are fetched
     */
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
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of users is always maximum, so return a scalar value.
     */
    protected function buildRelevanceExpression(): string
    {
        return '1';
    }

    /**
     * The title of a user result is always their full name (preferred if
     * present, otherwise fall back to first name).
     */
    protected function buildTitleExpression(): string
    {
        return "CONCAT(u.chosen_name, ' ', u.lastname)";
    }

    /**
     * The type of a user result will be "user.$roleType".
     */
    protected function buildTypeExpression(): string
    {
        return "CONCAT('user.', r.group_type)";
    }

    /**
     * The ID of a user result will simply be the user's ID.
     */
    protected function buildIdExpression(): string
    {
        return 'u.id';
    }

    /**
     * The date of a user result will be their last updated date.
     */
    protected function buildDateExpression(): string
    {
        return 'u.updated_at';
    }

    /**
     * Users have no recorded creator: thus, this will always be 0.
     */
    protected function buildCreatorExpression(): string
    {
        return '0';
    }

    /**
     * User results need to be fetched from the user and groups (a.k.a. roles)
     * tables.
     */
    protected function buildFromStatement(): string
    {
        return "
            FROM `user` u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
        ";
    }
}
