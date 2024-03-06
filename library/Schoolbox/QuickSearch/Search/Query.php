<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\QuickSearch\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
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
        private readonly IConnector $db,
        private readonly IConfigLoader $config,
        private readonly RosettaStone $l10n
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

        $uam = new UserAccessMapper();
        $allPermissions = $uam->findAllForUser($viewer);
        $permissionInfoByAccessibleFolderId = (
            $allPermissions
            ->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
            ->indexBy('serviceIdentifier')
        );
        $accessibleFolderIds = $permissionInfoByAccessibleFolderId->keys();

        $result = [
            'users' => [],
            'folders' => [],
        ];

        if ($keyword !== '' && \strlen($keyword) >= self::MIN_SEARCH_LENGTH) {
            $matchingUserIds = $this->getUserIdsMatchingKeyword($keyword, $viewer);
            $matchingAccessibleFolderIds = $this->getFolderIdsMatchingKeyword($keyword, $accessibleFolderIds);

            if (count($matchingUserIds) > 0) {
                $result['users']['data'] = $this->getUserData($matchingUserIds);
                $result['users']['totalCount'] = $this->getTotalCountForUsers($keyword, $viewer);
            }

            if (count($matchingAccessibleFolderIds) > 0) {
                $result['folders']['data'] = $this->getFolderData($matchingAccessibleFolderIds, $permissionInfoByAccessibleFolderId);
                $result['folders']['totalCount'] = count($matchingAccessibleFolderIds);
            }
        }

        return $result;
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
    private function getUserIdsMatchingKeyword(string $keyword, User $viewer): array
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
     * Find IDs for folders that match the given keyword.
     *
     * This list is subject to be filtered by `getQueryFilterForFolders`.
     *
     * @param string $keyword
     * @param array  $accessibleFolderIds
     *
     * @return array
     */
    private function getFolderIdsMatchingKeyword(string $keyword, array $accessibleFolderIds): array
    {
        $whereParts = [
            '(f.name LIKE :keywordForLike OR fc.code LIKE :keywordForLike)',
            'f.deleted_at IS NULL',
            'f.id IN (' . implode(',', array_map('intval', $accessibleFolderIds)) . ')'
        ];
        $params = ['keywordForLike' => '%' . $this->db->getQuoter()->escapeForLike($keyword) . '%'];

        $statement = $this->db->prepareQuery(
            // No need to sort because sorting is done in $this->getFolderData().
            'SELECT DISTINCT f.id
            FROM folder f
            LEFT JOIN folder_code fc ON fc.folder_id = f.id
            WHERE ' . implode(' AND ', $whereParts) . '
            GROUP BY f.id
            LIMIT ' . self::AUTOSUGGEST_LIMIT
        );

        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
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
                'id'             => $userId,
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
     * @param array $folderIds
     * @param Collection $permissionInfoByFolderId array of UserAccess
     *
     * @return array of arrays containing folder data.
     */
    private function getFolderData(array $folderIds, Collection $permissionInfoByFolderId): array
    {
        $folderById = FolderMapper::getInstance()->findByColumn(
            'id',
            $folderIds,
            [
                'order' => [
                    'updated_at' => 'DESC',
                ]
            ]
        );

        $folderData = [];
        foreach ($folderById as $folderId => $folder) {
            /** @var FolderModel $folder */

            /** @var UserAccess|null $userAccessToFolder */
            $userAccessToFolder = $permissionInfoByFolderId[$folderId];

            if ($userAccessToFolder !== null) {
                $folderData[] = [
                    'id'          => $folderId,
                    'name'        => $folder->getNameWithCodes(),
                    'metadata'    => $this->formatMetadataForFolder($folder),
                    'accessLevel' => $userAccessToFolder->getAccessLevel(),
                ];
            }
        }

        usort(
            $folderData,
            function ($a, $b): int {
                // If accessLevels are equal...
                if ($a['accessLevel'] === $b['accessLevel']) {
                    // Sort by name asc.
                    return $a['name'] <=> $b['name'];
                }

                // Sort by accessLevel desc.
                return $b['accessLevel'] <=> $a['accessLevel'];
            }
        );

        return $folderData;
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
            $whereParts[] = "g.group_type != :studentType";
            $params['studentType'] = Role::STUDENT;
        }
        if (!$findParents) {
            $whereParts[] = "g.group_type != :parentType";
            $params['parentType'] = Role::PARENT;
        }

        // guest should not appear
        $whereParts[] = "g.group_type != :guestType";
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
     * This is the same line that shows in search results underneath a folder result.
     *
     * @param FolderModel $folder
     * @return string
     */
    private function formatMetadataForFolder(FolderModel $folder): string
    {
        // TODO: Generate breadcrumbs to class page here.

        return '';
    }
}
