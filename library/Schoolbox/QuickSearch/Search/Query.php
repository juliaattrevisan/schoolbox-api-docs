<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\QuickSearch\Search;

use Alaress\Database\Connector\IConnector;
use Alaress\Image\Transformation\Storage;
use PDO;
use Psr\Http\Message\UriInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Role;
use Schoolbox\Url\Uri;
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

        $result = [
            'users' => [],
            'folders' => [],
        ];

        if ($keyword !== '' && strlen($keyword) >= self::MIN_SEARCH_LENGTH) {
            $matchingUserIds = $this->getUserIdsMatchingKeyword($keyword, $viewer);
            $matchingFolderIds = $this->getFolderIdsMatchingKeyword($keyword, $viewer);

            if (count($matchingUserIds) > 0) {
                $result['users']['data'] = $this->getUserData($matchingUserIds);
                $result['users']['totalCount'] = $this->getTotalCountForUsers($keyword, $viewer);
            }

            if (count($matchingFolderIds) > 0) {
                $result['folders']['data'] = $this->getFolderData($matchingFolderIds, $viewer);
                $result['folders']['totalCount'] = $this->getTotalCountForFolders($keyword);
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

        return $statement->fetchAll(PDO::FETCH_COLUMN);
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
     * Find IDs for folders that match the given keyword.
     *
     * This list is subject to be filtered by `getQueryFilterForFolders`.
     *
     * @param string $keyword
     * @param User $viewer
     *
     * @return array
     */
    private function getFolderIdsMatchingKeyword(string $keyword, User $viewer): array
    {
        $uam = new UserAccessMapper();
        $allPermissions = $uam->findAllForUser($viewer);
        $permissionInfoByInvolvedFolderId = (
        $allPermissions
            ->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
            ->indexBy('serviceIdentifier')
        );
        $involvedFolderIds = $permissionInfoByInvolvedFolderId->keys();

        [$where, $params] = $this->getQueryFilterForFolders($keyword);

        $statement = $this->db->prepareQuery(
            "SELECT DISTINCT
            f.id,
            IF(f.id IN (" . implode(',', array_map('intval', $involvedFolderIds)) . "), 1, 0) AS userIsInvolved
            FROM folder f
            LEFT JOIN folder_code fc ON fc.folder_id = f.id
            $where
            GROUP BY f.id
            ORDER BY userIsInvolved DESC, f.name ASC"
            // No LIMIT clause here because permission checks cannot be done in the SQL query,
            // and we don't know how many rows will remain after permission checks.
        );

        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Find a total count of folders that match the given keyword.
     *
     * This count is subject to be filtered by `getQueryFilterForFolders`.
     *
     * @param string $keyword
     *
     * @return int
     */
    private function getTotalCountForFolders(string $keyword): int
    {
        [$where, $params] = $this->getQueryFilterForFolders($keyword);
        $statement = $this->db->prepareQuery(
            "SELECT COUNT(DISTINCT f.id)
            FROM folder f
            LEFT JOIN folder_code fc ON fc.folder_id = f.id
            $where"
        );

        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Build the WHERE statement and query parameters which filter the list and
     * count of folders found by the search.
     *
     * @param $keyword
     *
     * @return array
     */
    private function getQueryFilterForFolders($keyword): array
    {
        $whereParts = [
            '(f.name LIKE :keywordForLike OR fc.code LIKE :keywordForLike)',
            'f.deleted_at IS NULL',
        ];
        $params = ['keywordForLike' => '%' . $this->db->getQuoter()->escapeForLike($keyword) . '%'];

        return [' WHERE ' . implode(' AND ', $whereParts), $params];
    }

    /**
     * @param array $folderIds
     * @param User $viewer
     *
     * @return array of arrays containing folder data.
     */
    private function getFolderData(array $folderIds, User $viewer): array
    {
        $access = Access::getInstance();
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

            try {
                if ($access->getFolderAccess($viewer, $folder->getId()) < UserAccess::ACCESS_READ) {
                    continue;
                }
            } catch (Exception) {
                // Problem determining access, so assume no access.
                continue;
            }

            $folderData[] = [
                'id'       => $folderId,
                'name'     => $folder->getNameWithCodes(),
                'metadata' => $this->formatMetadataForFolder($folder, $viewer, $access),
                'image'    => $folder->getCoverImage()
                    ? $this->buildStorageImageUrl($folder->getCoverImage())->__toString()
                    : null,
            ];

            if (count($folderData) >= self::AUTOSUGGEST_LIMIT) {
                break;
            }
        }

        // Note $folderData is already sorted in relevance order because of ORDER BY clause in getFolderIdsMatchingKeyword().
        return $folderData;
    }

    /**
     * Outputs a metadata line for this student.
     *
     * This is the same line that shows in search results underneath a folder result.
     *
     * @param FolderModel $folder
     * @param User $viewer
     * @param Access $access
     * @return array
     */
    private function formatMetadataForFolder(FolderModel $folder, User $viewer, Access $access): array
    {
        $folderMapper = new FolderMapper();

        try {
            $parents = $folderMapper->findParents($folder);
        } catch (Exception) {
            return []; // Orphans get no metadata :(
        }

        $breadcrumbs = [];
        foreach ($parents as $parent) {
            /** @var FolderModel $parent */
            try {
                $accessLevel = $access->getFolderAccess($viewer, $parent->getId());
            } catch (Exception) {
                $accessLevel = 0; // Problem getting access level, so assume no access.
            }

            if ($accessLevel >= UserAccess::ACCESS_READ) {
                $uri = new Uri();
                $breadcrumbs[] = ['name' => $parent->getName(), 'url' => $uri->withPath('/homepage/' . $parent->getId())];
            } else {
                $breadcrumbs[] = ['name' => $parent->getName(), 'url' => ''];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Helper for building a URL for an image in storage (copied from Search/Api/Hydrator/BaseHydrator.php).
     *
     * @param IStorage $storage The storage file.
     *                          "square64".
     * @return UriInterface
     */
    private function buildStorageImageUrl(
        IStorage $storage,
    ): UriInterface {
        $uri = new Uri();
        return $uri
            ->withPath('/storage/image.php')
            ->withQuery(
                http_build_query([
                    'hash' => $storage->getHash(),
                    'size' => Storage::TRANSFORM_SQUARE64,
                ])
            );
    }
}
