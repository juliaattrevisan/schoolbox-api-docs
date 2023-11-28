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
     *
     * @inheritdoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        // Don't show deleted users
        $whereParts = ['u.deleted_at IS NULL'];

        // Show disabled users only if ticked (hide when unticked)
        if (!$this->config->get('show_disabled_users')) {
            $whereParts[] = 'u.enabled = 1';
        }

        // Don't allow LIKE searches on keywords < 2 chars long
        // Probably shouldn't allow any searches on < 2 chars, but shrug
        $keywordSearchParts = ['u.synergy_id = :userKeyword'];
        $params             = ['userKeyword' => $keyword];
        if (mb_strlen($keyword, self::ENCODING) >= 2) {
            $keywordSearchParts[]      = "u.username LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "u.firstname LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "u.preferred_name LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "u.lastname LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.title, u.firstname, u.lastname) LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.firstname, u.lastname) LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.title, u.preferred_name, u.lastname) LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.preferred_name, u.lastname) LIKE :userKeywordLike$paramKeySuffix";
            $keywordSearchParts[]      = "CONCAT_WS(' ', u.title, u.lastname) LIKE :userKeywordLike$paramKeySuffix";
            $params['userKeywordLike'] = '%' . $this->quoter->escapeForLike($keyword) . '%';
        }
        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of users is always maximum, so return a static value.
     *
     * @inheritdoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return '1';
    }

    /**
     * Since `buildRelevanceExpression` returns a static value, user results are
     * not ordered by relevance.
     *
     * @inheritdoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return false;
    }

    /**
     * The title of a user result is always their full name (preferred if
     * present, otherwise fall back to first name).
     *
     * @inheritdoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return "CONCAT_WS(' ', u.chosen_name, u.lastname)";
    }

    /**
     * @inheritdoc
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * A user's type will be "user.$roleType".
     *
     * @inheritdoc
     */
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        return "CONCAT('" . Hydrator::BASE_TYPE . ".', r.group_type)";
    }

    /**
     * The ID of a user result will simply be the user's ID.
     *
     * @inheritdoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'u.id';
    }

    /**
     * The date of a user result will be their last updated date.
     *
     * @inheritdoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'u.updated_at';
    }

    /**
     * Users have no recorded creator: thus, this will always be null.
     *
     * @inheritdoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): ?string
    {
        return null;
    }

    /**
     * Users do not have folders, thus this returns `null`.
     *
     * @inheritdoc
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): ?string
    {
        return null;
    }

    /**
     * User results need to be fetched from the user and groups (a.k.a. roles)
     * tables.
     *
     * @inheritdoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return "
            FROM `user` u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
        ";
    }
}
