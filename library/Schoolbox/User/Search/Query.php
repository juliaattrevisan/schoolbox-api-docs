<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Query
{
    /**
     * Number of users that will show on autosuggest field
     */
    public const AUTOSUGGEST_LIMIT = 5;

    public const MIN_SEARCH_LENGTH = 2;

    /**
     * Query constructor.
     *
     * @param IConnector    $db
     * @param IConfigLoader $config
     * @param RosettaStone  $l18n
     */
    public function __construct(
        private IConnector $db,
        private IConfigLoader $config,
        private RosettaStone $l18n
    ) {
    }

    /**
     * Find the search results.
     *
     * @param string $keyword What's the keyword `$viewer` is searching for?
     * @param User   $viewer  Who's viewing the results? This affects what they
     *                        may see.
     *
     * @return array
     */
    public function fetchResults(string $keyword, User $viewer): array
    {
        $keyword = trim($keyword);

        $result = [
            'results'       => [],
            'totalCount'    => 0,
        ];

        if ($keyword !== '' && \strlen($keyword) >= self::MIN_SEARCH_LENGTH) {
            $userIds = $this->getUserIdsByKeyword($keyword, $viewer);
            if ($userIds) {
                $result['results']    = $this->getUserData($userIds);
                $result['totalCount'] = $this->getTotalCount($keyword, $viewer);
            }
        }

        return $result;
    }

    /**
     * @param array $userIds
     *
     * @return array
     */
    private function getUserData(array $userIds): array
    {
        $result = [];

        $users = UserMapper::getInstance()->findByColumn(
            'id',
            $userIds,
            [
                'order' => [
                    'lastname'   => 'ASC',
                    'chosen_name' => 'ASC',
                ]
            ]
        );

        foreach ($users as $key => $user) {
            $result[$key]['id']             = $user->getId();
            $result[$key]['externalId']     = $user->getExternalId();
            $result[$key]['firstname']      = $user->getFirstname();
            $result[$key]['preferredName']  = $user->getPreferredName();
            $result[$key]['lastname']       = $user->getLastname();
            $result[$key]['formattedName']  = $user->formatName();
            $result[$key]['metadata']       = $this->formatMetadata($user);
            $result[$key]['year']           = $user->getYear() ? $user->getYear()->getName() : '';
        }

        return $result;
    }

    /**
     * Find IDs for users that match the given keyword.
     *
     * This list is subject to be filtered by `getQueryFilter`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array
     */
    private function getUserIdsByKeyword(string $keyword, User $viewer): array
    {
        [$where, $params] = $this->getQueryFilter($keyword, $viewer);

        $statement = $this->db->prepareQuery(
            'SELECT DISTINCT u.id FROM user u INNER JOIN `groups` g ON u.group_id = g.id'
            . $where
            . ' ORDER BY u.lastname, u.chosen_name ASC LIMIT 0, ' . self::AUTOSUGGEST_LIMIT
        );

        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Find a total count of users that match the given keyword.
     *
     * This count is subject to be filtered by `getQueryFilter`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return int
     */
    private function getTotalCount(string $keyword, User $viewer): int
    {
        [$where, $params] = $this->getQueryFilter($keyword, $viewer);
        $statement = $this->db->prepareQuery(
            'SELECT COUNT(DISTINCT u.id) FROM user u INNER JOIN `groups` g ON u.group_id = g.id' . $where
        );

        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Build the WHERE statement and query parameters which filter the list and
     * count of users found by the search.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array [$where, $params]
     */
    private function getQueryFilter(string $keyword, User $viewer): array
    {
        $searchString = '%' . $this->db->getQuoter()->escapeForLike($keyword) . '%';

        $whereParts = [
            '(
                CONCAT_WS(" ", u.title, u.firstname, u.lastname) LIKE :firstLastNameWithTitle
                OR CONCAT_WS(" ", u.firstname, u.lastname) LIKE :firstAndLastNameOnly
                OR CONCAT_WS(" ", u.title, u.preferred_name, u.lastname) LIKE :preferredLastNameWithTitle
                OR CONCAT_WS(" ", u.preferred_name, u.lastname) LIKE :preferredAndLastNameOnly
                OR CONCAT_WS(" ", u.title, u.lastname) LIKE :titleAndLastnameOnly
                OR u.firstname LIKE :firstname
                OR u.preferred_name LIKE :preferred_name
                OR u.lastname LIKE :lastname
                OR u.username LIKE :username
            )',
            'u.deleted_at IS NULL'
        ];

        $params = [
            'firstLastNameWithTitle'     => $searchString,
            'firstAndLastNameOnly'       => $searchString,
            'preferredLastNameWithTitle' => $searchString,
            'preferredAndLastNameOnly'   => $searchString,
            'titleAndLastnameOnly'       => $searchString,
            'firstname'                  => $searchString,
            'preferred_name'             => $searchString,
            'lastname'                   => $searchString,
            'username'                   => $searchString,
        ];

        /**
         * When show_disabled_users flag is set to TRUE, disabled users will be shown in the result
         * Otherwise, only active users will be shown
         */
        if (!$this->config->get('show_disabled_users')) {
            $whereParts[] = 'u.enabled = 1';
        }

        // Superuser may always view all users including guests
        // Staff may always view parents and students
        // Guests may never view parents and students
        // Parents and students may only view parents or students if the config
        // allows it
        if ($viewer->getRole()->isGuest()) {
            $findStudents = false;
            $findParents  = false;
        } elseif ($viewer->getRole()->isStaff()) {
            $findStudents = true;
            $findParents  = true;
        } else {
            $findStudents = $this->config->get('student_search_enable');
            $findParents  = $this->config->get('parent_search_enable');
        }

        if (!$findStudents) {
            $whereParts[]          = "g.group_type != :studentType";
            $params['studentType'] = Role::STUDENT;
        }
        if (!$findParents) {
            $whereParts[]         = "g.group_type != :parentType";
            $params['parentType'] = Role::PARENT;
        }

        // guest should not appear
        $whereParts[]        = "g.group_type != :guestType";
        $params['guestType'] = Role::GUEST;

        return [' WHERE ' . implode(' AND ', $whereParts), $params];
    }

    /**
     * Outputs a metadata line for this student.
     *
     * This is the same line that shows in search results underneath a student's name.
     *
     * @param User $user
     * @return string
     * @todo allow schools to set the format as they can with `$this->formatName`
     *
     */
    private function formatMetadata(User $user): string
    {
        // For now, just show role name
        if (!empty($user->getPositionTitle())) {
            return $user->getPositionTitle();
        }

        $metadata = '';
        $role     = $user->getRole()->getName();

        /* This made a massive assumption that everyone's year level
         * begins with "Year", which means anyone using "ELC" in lu_year_level.name
         * would get metadata like "Year ELC"
         *
         * See inside getLongName() which checks whether it's just an integer on its own
         * before trying to prepend something
         */
        if ($user->getYear()) {
            $metadata .= $user->getYear()->getLongName($this->l18n->translate('year', false, true));
        }
        return "{$metadata} {$role}";
    }
}
