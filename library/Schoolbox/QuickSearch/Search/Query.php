<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\QuickSearch\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Query
{
    /**
     * Number of users that will show on auto-suggest field
     */
    public const AUTOSUGGEST_LIMIT = 5;

    public const MIN_SEARCH_LENGTH = 2;

    /**
     * Query constructor.
     *
     * @param IConnector    $db
     * @param IConfigLoader $config
     * @param RosettaStone  $l10n
     */
    public function __construct(
        private IConnector $db,
        private IConfigLoader $config,
        private RosettaStone $l10n
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
            'users' => [],
            'classFolders' => [],
        ];

        if ($keyword !== '' && \strlen($keyword) >= self::MIN_SEARCH_LENGTH) {
            $userIds = $this->getUserIdsByKeyword($keyword, $viewer);
            $classFolderIds = $this->getClassFolderIdsByKeyword($keyword, $viewer);

            if (count($userIds) > 0) {
                $result['users']['data'] = $this->getUserData($userIds);
                $result['users']['totalCount'] = $this->getTotalCountForUsers($keyword, $viewer);
            }

            if (count($classFolderIds) > 0) {
                $result['classFolders']['data'] = $this->getClassFolder($classFolderIds, $viewer);
                $result['classFolders']['totalCount'] = $this->getTotalCountForClassFolders($keyword, $viewer);
            }
        }

        return $result;
    }

    /**
     * @param array $userIds
     *
     * @return array of arrays containing user data.
     */
    private function getUserData(array $userIds): array
    {
        $userByUserId = UserMapper::getInstance()->findByColumn(
            'id',
            $userIds,
            [
                'order' => [
                    'lastname'   => 'ASC',
                    'chosen_name' => 'ASC',
                ]
            ]
        );

        $userData = [];
        foreach ($userByUserId as $userId => $user) {
            $userData[] = [
                'id'             => $user->getId(),
                'externalId'     => $user->getExternalId(),
                'firstname'      => $user->getFirstname(),
                'preferredName'  => $user->getPreferredName(),
                'lastname'       => $user->getLastname(),
                'formattedName'  => $user->formatName(),
                'metadata'       => $this->formatMetadataForUser($user),
                'year'           => $user->getYear() ? $user->getYear()->getName() : '',
            ];
        }

        return $userData;
    }

    /**
     * @param array $classFolderIds
     * @param User $viewer
     *
     * @return array of arrays containing class folder data.
     */
    private function getClassFolder(array $classFolderIds, User $viewer): array
    {
        $classFolderById = FolderMapper::getInstance()->findByColumn(
            'id',
            $classFolderIds,
            [
                'order' => [
                    'updated_at' => 'DESC',
                ]
            ]
        );

        $externalUser = new \External_User();
        $subjectList = $externalUser->getSubjectList($viewer);
        $subjectListNamesAsKeys = [];

        foreach ($subjectList as $subject) {
            $subjectListNamesAsKeys[$subject['name']] = null;
        }

        $classFolderData = [];
        foreach ($classFolderById as $classFolderId => $classFolder) {
            /* @var FolderModel $classFolder */
            $classFolderName = $classFolder->getName();
            $classFolderData[] = [
                'id'             => $classFolderId,
                'name'           => $classFolderName,
                'codesString'    => $classFolder->getCodeString(),
                'metadata'       => $this->formatMetadataForClassFolder($classFolder),
                'userIsInvolved' => array_key_exists($classFolderName, $subjectListNamesAsKeys)
            ];
        }

        usort(
            $classFolderData, function ($a, $b): int
            {
                return (
                    // Sort by userIsInvolved desc...
                    ($a['userIsInvolved'] && !$b['userIsInvolved']) ? -1 :
                    (
                        (!$a['userIsInvolved'] && $b['userIsInvolved']) ? 1 :
                        (
                            // Sort by name asc,
                            ($a['name'] > $b['name']) ? 1 :
                            (
                                ($a['name'] < $b['name']) ? -1 : 0
                            )
                        )
                    )
                );
            }
        );

        return $classFolderData;
    }

    /**
     * Find IDs for users that match the given keyword.
     *
     * This list is subject to be filtered by `getQueryFilterForUsers`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array
     */
    private function getUserIdsByKeyword(string $keyword, User $viewer): array
    {
        [$where, $params] = $this->getQueryFilterForUsers($keyword, $viewer);

        $statement = $this->db->prepareQuery(
            "SELECT DISTINCT u.id
            FROM user u
            INNER JOIN `groups` g ON u.group_id = g.id
            $where
            ORDER BY u.lastname ASC, u.chosen_name ASC
            LIMIT " . self::AUTOSUGGEST_LIMIT
        );

        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Find IDs for class folders that match the given keyword.
     *
     * This list is subject to be filtered by `getQueryFilterForClassFolders`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array
     */
    private function getClassFolderIdsByKeyword(string $keyword, User $viewer): array
    {
        [$where, $params] = $this->getQueryFilterForClassFolders($keyword, $viewer);

        $statement = $this->db->prepareQuery(
            "SELECT DISTINCT f.id
            FROM folder f
            JOIN folder_code fc ON fc.folder_id = f.id
            $where
            GROUP BY f.id
            ORDER BY f.updated_at, f.name
            LIMIT " . self::AUTOSUGGEST_LIMIT
        );

        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Find a total count of users that match the given keyword.
     *
     * This count is subject to be filtered by `getQueryFilterForUsers`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return int
     */
    private function getTotalCountForUsers(string $keyword, User $viewer): int
    {
        [$where, $params] = $this->getQueryFilterForUsers($keyword, $viewer);
        $statement = $this->db->prepareQuery(
            "SELECT COUNT(DISTINCT u.id)
            FROM user u
            INNER JOIN `groups` g ON u.group_id = g.id\n" .
            $where
        );

        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Find a total count of class folders that match the given keyword.
     *
     * This count is subject to be filtered by `getQueryFilterForClassFolders`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return int
     */
    private function getTotalCountForClassFolders(string $keyword, User $viewer): int
    {
        [$where, $params] = $this->getQueryFilterForClassFolders($keyword, $viewer);
        $statement = $this->db->prepareQuery(
            "SELECT COUNT(DISTINCT f.id)
            FROM folder f
            JOIN folder_code fc ON fc.folder_id = f.id
            $where"
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
    private function getQueryFilterForUsers(string $keyword, User $viewer): array
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
     * Build the WHERE statement and query parameters which filter the list and
     * count of users found by the search.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array [$where, $params]
     */
    private function getQueryFilterForClassFolders(string $keyword, User $viewer): array
    {
        $whereParts = [
            'fc.code LIKE :keyword',
            'f.deleted_at IS NULL'
        ];
        $params = ['keyword' => '%' . $this->db->getQuoter()->escapeForLike($keyword) . '%'];

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
    private function formatMetadataForUser(User $user): string
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
            $metadata .= $user->getYear()->getLongName($this->l10n->translate('year', false, true));
        }
        return "$metadata $role";
    }

    /**
     * Outputs a metadata line for this student.
     *
     * This is the same line that shows in search results underneath a class folder result.
     *
     * @param FolderModel $classFolder
     * @return string
     */
    private function formatMetadataForClassFolder(FolderModel $classFolder): string
    {
        return $classFolder->getName();
    }
}
