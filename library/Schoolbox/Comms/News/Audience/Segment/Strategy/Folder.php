<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\Mysql\Quoter;
use External_Class;
use External_ClassInterface;
use InvalidArgumentException;
use PDO;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\NNIAN;
use Schoolbox\User;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use External_UserInterface;
use UnderflowException;
use UnexpectedValueException;

use function array_intersect;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function implode;
use function in_array;
use function sprintf;

class Folder extends SimpleStrategy
{

    /** @var bool */
    private $canTargetTeachersOfUsersInFolder;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var External_ClassInterface */
    private $externalClass;

    /**
     * Set of subject data arrays, grouped by user.id
     *
     * Same format as used in session
     *
     * @see \Session.php
     * @var mixed[][][]
     */
    private $knownUserSubjects;


    /**
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'folder';
    }


    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may have no involved folders
        return false;
//        return true; // TODO Turned off as not-yet implemented in front-end so no need to include in query
    }


    /**
     * @param UserAccessMapper       $userAccessMapper
     * @param Access                 $access
     * @param bool                   $staffCanPost
     * @param bool                   $moderationRequired
     * @param bool                   $canTargetTeachersOfUsersInFolder
     * @param FolderMapper           $folderMapper
     * @param RoleAccessMapper       $roleAccessMapper
     * @param External_UserInterface $externalUser
     * @param mixed[][][]            $knownUserSubjects
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        Access $access,
        bool $staffCanPost,
        bool $moderationRequired,
        bool $canTargetTeachersOfUsersInFolder,
        FolderMapper $folderMapper,
        RoleAccessMapper $roleAccessMapper,
        External_UserInterface $externalUser,
        array $knownUserSubjects
    ) {
        parent::__construct($userAccessMapper, $access, $staffCanPost, $moderationRequired);
        $this->canTargetTeachersOfUsersInFolder = $canTargetTeachersOfUsersInFolder;
        $this->folderMapper                     = $folderMapper;
        $this->roleAccessMapper                 = $roleAccessMapper;
        $this->externalUser                     = $externalUser;
        $this->knownUserSubjects                = $knownUserSubjects;
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        return $this->canTargetTeachersOfUsersInFolder
            ? [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF]
            : [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF];
    }


    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user): string
    {
        return '
            SELECT cid
            FROM perms
            WHERE gid = ' . $user->getRole()->getId() . '
            AND type > ' . UserAccess::ACCESS_DENY;
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityType(User $user): bool
    {
        // All users can always create folder audience segments
        return true;
    }


    /** @inheritdoc */
    public function getTranslateSql(array $entityValues): Audience\Segment\TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'folder'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        $sql = "
            SELECT
                '{$entityType}' entity_type,
                folder.id entity_value,
                CONCAT(name, IF(COUNT(code) > 0, CONCAT(' (', GROUP_CONCAT(code SEPARATOR ', '), ')'), '')) name
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE {$pb->getWhereIntersect()}
            GROUP BY folder.id
        ";
        return new Audience\Segment\TranslateSql($sql, $pb->getParams());
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityValue(User $user, ?string $entityValue): bool
    {
        // Is the user allowed to audience
        if ($this->canAudienceToAnyClassOrGroup($user)) {
            return true;
        }

        // Other users must have WRITE+ perm to the folder
        if (null === $entityValue) {
            return false;
        }
        return $this->getAccess()->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_WRITE;
    }


    /**
     * Returns whether the user has at least the specified CMS Service perms
     *
     * @param User $user
     * @param int  $level
     * @return bool
     */
    private function hasCmsServicePerm(User $user, int $level): bool
    {
        return $this->getAccess()->getAccessLevel($user, UserAccess::SERVICE_CMS) >= $level;
    }


    /**
     * @inheritdoc
     * @throws InvalidColumnException
     * @throws Mapper\DefinitionException
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string
    {
        $folderMembershipSql = '';
        $entityType          = self::getEntityType();

        if (!$this->canAudienceToAnyClassOrGroup($user)) {
            // Get folders where user has required access via group/class individual permissions
            $groupAndClassMembership = $this->folderMapper->findFolderIdsForUser($user, true, UserAccess::ACCESS_WRITE);

            // Get folders where user has required access via their role permissions
            $roleAccess = $this->findFolderIdsWithRoleAccess($user, UserAccess::ACCESS_WRITE);

            // Combine the two
            $folderMembership = \array_unique(array_merge($groupAndClassMembership, $roleAccess));

            /* If I have zero elements in my folderMembership, boot me out - there's nothing to fetch
             * I'm restricted from seeing any folders
             */
            if (!count($folderMembership)) {
                return null;
            }

            /* Otherwise, restrict my search results to these folders only
             */
            $folderMembershipSql = ' AND folder.id IN (' . implode(', ', $folderMembership) . ')';
        }

        $keywordFilter = null === $searchPlaceholder ? '' : "AND (folder.name LIKE :{$searchPlaceholder} OR folder_code.code LIKE :{$searchPlaceholder})";

        return "
            SELECT folder.id,
                CONCAT(folder.name,
                    CASE
                    WHEN COUNT(folder_code.code) > 0
                    THEN CONCAT(' (', GROUP_CONCAT(folder_code.code SEPARATOR ', '), ')')
                    ELSE ''
                    END) AS `name`,
                '{$entityType}' AS entity_type, null as entity_meta
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE folder.name IS NOT NULL
            AND (
                folder.id IN (SELECT folder_id FROM folder_code WHERE deleted_at IS NULL)
                OR
                folder.id IN (SELECT cid FROM userGroups)
            )
            {$keywordFilter}
            {$folderMembershipSql}
            AND folder.deleted_at IS NULL
            GROUP BY folder.id
            ORDER BY folder.name
        ";
    }


    /**
     * @inheritdoc
     */
    public function canViewArticleAudiencedToEntityValue(User $user, ?string $entityValue, string $modifier): bool
    {
        if (null === $entityValue) {
            // No users have special relationships to no folder
            return false;
        }
        if ($modifier !== Audience\Model::RELATIONSHIP_SELF) {
            // If this is segment is for parents or teachers:
            // it is not intended for the current user.
            return false;
        }
        // The user must still have READ perm to the page
        return $this->getAccess()->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_READ;
    }


    /**
     * @@inheritdoc
     */
    public function canModerate(User $user, ?string $entityValue): bool
    {
        // Special case for folders
        if (
            $user->isSuperuser() ||
            $this->hasCmsServicePerm($user, UserAccess::ACCESS_WRITE) ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE)
        ) {
            // These can all moderated, regardless of whether segment points to a valid folder
            return true;
        }

        // No other user can moderate if value not set
        if (null === $entityValue) {
            return false;
        }

        try {
            // Other users can moderate if folder exists, and user has WRITE+ perm
            return $this->getAccess()->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_WRITE;
        } catch (Mapper\Exception\Deleted | Mapper\Exception\NotFound $e) {
            // If deleted or not found, then cannot be moderated
            return false;
        }
    }


    /**
     * @inheritdoc
     * @param GenericCollection|User[] $users
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    public function getValues(GenericCollection $users): array
    {
        $userIds = $users->getPropertyValues('id');

        // Get groups where learner or instructor (but not admin) TODO that's not what this code is doing??
        $pb             = $this->getUserAccessMapper()->getPredicateBuilder()
                               ->in('uid', $userIds)
                               ->equals('service', UserAccess::SERVICE_HOMEPAGE)
                               ->notEquals('type', UserAccess::ACCESS_DENY);
        $groupFolderIds = $this->getUserAccessMapper()->findScalarByPredicates('sid', $pb);

        // Get subjects
        $subjectLists = [];
        foreach ($users as $user) {
            // Attempt to use cached value if already known for this user
            if (\array_key_exists($user->getId(), $this->knownUserSubjects)) {
                $subjectList = $this->knownUserSubjects[$user->getId()];
            } else {
                $subjectList = $this->externalUser->getSubjectList($user);
            }
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $subjectLists = array_merge($subjectLists, $subjectList);
        }

        $classFolderIds = $this->folderMapper->findAllBySubjectList($subjectLists)->getPropertyValues('id');

        return \array_unique(array_merge($groupFolderIds, $classFolderIds));
    }


    /**
     * Returns whether the specified user is allowed to audience to any folder (include NULL/no folder)
     *
     * @param User $user
     * @return bool
     */
    public function canAudienceToAnyClassOrGroup(User $user): bool
    {
        return
            $user->isSuperuser() ||
            $this->hasCmsServicePerm($user, UserAccess::ACCESS_WRITE) ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE);
    }


    /**
     * Returns the set of folder ids where $user has the specified access or greater due to role permissions
     *
     * @param User $user
     * @param int  $accessLevel
     * @return int[]
     * @throws InvalidColumnException
     */
    private function findFolderIdsWithRoleAccess(User $user, int $accessLevel): array
    {
        $pb = $this->roleAccessMapper->getPredicateBuilder()
                                     ->equals('gid', $user->getRole()->getId())
                                     ->greaterThanOrEqualTo('type', $accessLevel);

        return $this->roleAccessMapper->findScalarByPredicates('cid', $pb);
    }


    /**
     * @inheritDoc
     * @param string $userTableAlias
     * @param string $paramPrefix
     * @param array  $entityValues
     * @param bool   $excludeMuted
     * @return UserResolutionSql
     */
    public function getUserResolutionSql(
        string $userTableAlias,
        string $paramPrefix,
        array $entityValues,
        bool $excludeMuted
    ): UserResolutionSql {
        $targetNull = self::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $filtered   = $this->filterOutNullValues($entityValues);
        if (!$targetNull && !count($filtered)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        // Always fetch members from NNIAN folders
        try {
            $nnianFolderIds = $this->findNnianFolderIds($filtered);
        } catch (Mapper\Exception $e) {
            trigger_error('Failed to get muted folders for ' . implode(',', $filtered) . ': ' . $e->getMessage(), E_USER_WARNING);
            $nnianFolderIds = [];
        }

        // For performance reasons, it is faster to UNION the GROUP + CLASS members, rather than LEFT JOIN them.
        // This is incompatible with the interface's return type. Therefore we will just resolve the user ids,
        // and return a predicate to match these directly. In any event, the overhead of fetching the class members
        // from the sis is much higher than this double tap.
        $userIds = $this->getUserIdsForFolders($nnianFolderIds, $targetNull, $excludeMuted);
        if (!count($userIds)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        return new UserResolutionSql('', $this->newPb($paramPrefix)->in("$userTableAlias.id", $userIds));
    }


    /**
     * In all cases, when a segment targets a folder, we fetch members from the folder's NNIAN.
     * This is because:
     * * a class/group never inherits permissions
     * * if segment was targeted to class/group, but class/group has since become a subFolder of class/group, we do
     *   want to fetch members of the new NNIAN folder
     *
     * @param string[]|null[] $folderIds
     * @return string[]|null[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function findNnianFolderIds(array $folderIds): array
    {
        $nnian          = NNIAN::getInstance($this->folderMapper);
        $nnianFolderIds = [];
        foreach ($folderIds as $folderId) {
            if ($folderId === null) {
                $nnianFolderIds[] = null;
                continue;
            }
            $folder = (new FolderModel())->setId((int)$folderId);
            try {
                $nnianFolder = $nnian->findNearestNonInheritingAncestralNode($folder);
            } catch (Mapper\Exception\Deleted $e) {
                // Ignore deleted folders
                continue;
            }

            $nnianFolderIds[] = (string)$nnianFolder->getId();
        }
        return array_unique($nnianFolderIds);
    }


    /**
     * Returns muted users per nnian folder.
     *
     * Mutes are always applied at the NNIAN level, regardless of whether a mute exists for the targeted folder,
     * as it is always the NNIAN folder displayed to the user in their settings
     *
     * @param null[]|string[] $nnianFolderIds
     * @return string[][]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    public function getMutedUsersPerFolder(array $nnianFolderIds): array
    {
        $filtered = $this->filterOutNullValues($nnianFolderIds);
        if (!count($filtered)) {
            return [];
        }
        $mutedUsersPerFolder = [];

        $pb = (new Builder(new Quoter(), 'msg_reroute_override'))
            ->in('msg_route_key', array_map(static function (string $folderId) {
                return sprintf('service.%s.%s', UserAccess::SERVICE_HOMEPAGE, $folderId);
            }, $filtered))
            ->equals('delivery', RoutePermission::MUTE);

        $sql   = "
            SELECT SUBSTRING_INDEX(msg_route_key, '.', -1) folder_id, user_id
            FROM msg_reroute_override
            WHERE {$pb->getWhereIntersect()}
        ";
        $query = $this->folderMapper->getDatabase()->query($sql, $pb->getParams());
        while ([$folderId, $userId] = $query->fetch(PDO::FETCH_NUM)) {
            $folderId = (string)$folderId;
            if ($userId === '0') {
                // Notification is disabled for this folder. Reset to '0' only.
                $mutedUsersPerFolder[$folderId] = ['0'];
            } elseif (!in_array('0', $mutedUsersPerFolder[$folderId] ?? [])) {
                // If notification is enabled, add current muted user
                $mutedUsersPerFolder[$folderId] ??= [];
                $mutedUsersPerFolder[$folderId][] = $userId;
            }
        }
        return $mutedUsersPerFolder;
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        switch ($relationship) {
            case Audience\Model::RELATIONSHIP_SELF:
                if ($user->getSubjectCollection() === null) {
                    return null;
                }
                // Cannot conclude false, because groups are not provided
                return count(array_intersect($this->parseSubjectIds($user), $entityValues)) ? true : null;
            case Audience\Model::RELATIONSHIP_PARENTS_OF:
                if (!$user->getRole()->isParent()) {
                    return false;
                }
                if ($user->getChildren() === null) {
                    return null;
                }
                foreach ($user->getChildren() as $child) {
                    if ($child->getSubjectCollection() === null) {
                        continue;
                    }
                    if (count(array_intersect($this->parseSubjectIds($child), $entityValues))) {
                        return true;
                    }
                }
                return null;
            case Audience\Model::RELATIONSHIP_TEACHERS_OF:
                if (!$user->getRole()->isStaff()) {
                    return false;
                }
                return null;
        }
        return null;
    }


    /**
     * Parse subject/class folder ids from a user's subject collection
     *
     * @param User $user
     * @return array
     */
    private function parseSubjectIds(User $user): array
    {
        return $user->getSubjectCollection()->map(static function (array $subject) {
            return $subject['id'];
        })->getAll();
    }


    /**
     * Get folder members, optionally excluding users who have muted those folders
     *
     * @param string[] $nnianFolderIds
     * @param bool     $targetNull
     * @param bool     $excludeMuted
     * @return int[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getUserIdsForFolders(array $nnianFolderIds, bool $targetNull, bool $excludeMuted): array
    {
        $quoter = new Quoter();

        $mutedUsersPerFolder = $excludeMuted ? $this->getMutedUsersPerFolder($nnianFolderIds) : [];

        if ($excludeMuted) {
            $mutedFolderIds = [];
            // Get notification disabled folders
            foreach ($mutedUsersPerFolder as $folderId => $userIds) {
                if ($userIds === ['0']) {
                    $mutedFolderIds[] = $folderId;
                }
            }
            // Remove notification disabled folders from target folders
            $nnianFolderIds = array_diff($nnianFolderIds, $mutedFolderIds);
        }

        // Get group users, regardless of whether they have read/write/mod perms. (but not deny)
        $groupJoin = $targetNull ? 'LEFT' : '';
        $groupPb   = (new Builder($quoter, ''));
        if (count($nnianFolderIds)) {
            $groupInPb = (new Builder($quoter, ''))
                ->equals('ua.service', UserAccess::SERVICE_HOMEPAGE)
                ->in('ua.sid', $nnianFolderIds)
                ->notEquals('ua.type', UserAccess::ACCESS_DENY);
            if ($targetNull) {
                // null + filtered
                $groupPb->any(
                    (new Builder($quoter, ''))
                        ->all($groupInPb)
                        ->isNull('ua.uid')
                );
            } else {
                // Just in $filtered
                $groupPb = $groupInPb;
            }
        } else {
            // just null
            $groupPb->isNull('ua.uid');
        }
        foreach ($mutedUsersPerFolder as $folderId => $userIds) {
            foreach ($userIds as $userId) {
                $groupPb->notAll((new Builder($quoter, ''))
                    ->equals('ua.uid', $userId)
                    ->equals('ua.sid', $folderId));
            }
        }

        $queries    = [];
        $queries[]  = "
            SELECT ua.uid id
            FROM user u
            {$groupJoin} JOIN userAccess ua ON u.id = ua.uid
            WHERE {$groupPb->getWhereIntersect()}
            AND u.deleted_at IS NULL
        ";
        $allParams  = [$groupPb->getParams()];

        // Associated with the class, regardless of role type.
        // For students/teachers you'd need to use a composite segment that intersects the desired roleType.
        if (count($nnianFolderIds)) {
            $folderCodesPerFolderId = $this->getFolderCodes($nnianFolderIds);

            if (count($folderCodesPerFolderId)) {
                if (count($mutedUsersPerFolder)) {
                    // We have mutes, therefore need to fetch student lists individually
                    foreach ($folderCodesPerFolderId as $folderId => $folderCodes) {
                        $mutedUsers = $mutedUsersPerFolder[$folderId] ?? [];
                        [$query, $params] = $this->getClassMembersSubQuery("_$folderId", $folderCodes, $mutedUsers);
                        $queries[]   = $query;
                        $allParams[] = $params;
                    }
                } else {
                    // No mutes, we can aggregate codes
                    [$query, $params] = $this->getClassMembersSubQuery("", array_merge(...$folderCodesPerFolderId));
                    $queries[]   = $query;
                    $allParams[] = $params;
                }
            }
        }

        $sql = implode("\nUNION\n", $queries);
        return $this->folderMapper->getDatabase()->query($sql, array_merge(...$allParams))->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Build a SQL fragment and params that will fetch folder members from specified folder codes,
     * optionally excluding specific users ids
     *
     * @param string   $paramPrefix
     * @param string[] $folderCodes
     * @param string[] $excludedUsers
     * @return array
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClassMembersSubQuery(string $paramPrefix, array $folderCodes, array $excludedUsers = []): array
    {
        $studentExternalIds = $this->getStudentExternalIds($folderCodes);
        $teacherExternalIds = $this->getTeacherExternalIds($folderCodes);
        if (!count($studentExternalIds) && !count($teacherExternalIds)) {
            return ['SELECT id FROM user WHERE false', []];
        }
        $quoter  = new Quoter();
        $classPb = (new Builder($quoter, ''))->setParamPrefix($paramPrefix);
        if (count($studentExternalIds)) {
            $classPb->all(
                (new Builder($quoter, ''))
                    ->setParamPrefix($paramPrefix)
                    ->in("u.synergy_id", $studentExternalIds)
                    ->equals("g.group_type", \Schoolbox\Role::STUDENT)
            );
        }
        if (count($teacherExternalIds)) {
            $classPb->all(
                (new Builder($quoter, ''))
                    ->setParamPrefix($paramPrefix)
                    ->in("u.synergy_id", $teacherExternalIds)
                    ->equals("g.group_type", \Schoolbox\Role::STAFF)
            );
        }
        $classPb = (new Builder($quoter, ''))->any($classPb);
        if (count($excludedUsers)) {
            $classPb->notIn('u.id', $excludedUsers);
        }
        $classPb->isNull("u.deleted_at");

        return [
            "SELECT u.id FROM user u JOIN `groups` g ON g.id = u.group_id WHERE {$classPb->getWhereIntersect()}",
            $classPb->getParams(),
        ];
    }


    /**
     * Returns student external ids for a set of folder codes
     *
     * @param string[] $folderCodes
     * @return string[]
     */
    private function getStudentExternalIds(array $folderCodes): array
    {
        $externalIds = [];
        foreach ($this->getExternalClass()->getStudents(implode(',', $folderCodes)) as $student) {
            $externalIds[] = $student['synergy_id'];
        }
        return $externalIds;
    }


    /**
     * Returns teacher external ids for a set of folder codes
     *
     * @param string[] $folderCodes
     * @return string[]
     */
    private function getTeacherExternalIds(array $folderCodes): array
    {
        $externalIds = [];
        foreach ($this->getExternalClass()->getTeachers(implode(',', $folderCodes)) as $teacherExternalId) {
            $externalIds[] = $teacherExternalId;
        }
        return $externalIds;
    }


    /**
     * Returns all folder codes for the specified folders, grouped by folder.id
     *
     * @param string[] $folderIds
     * @return string[][]
     */
    private function getFolderCodes(array $folderIds): array
    {
        if (!count($folderIds)) {
            return [];
        }
        $folderCodesPerFolderId = [];

        $bindings = Builder::getBindings($folderIds, 'fid');
        $query    = $this->folderMapper->getDatabase()->query(
            "
            SELECT f.id folder_id, code
            FROM folder_code fc
            JOIN folder f ON fc.folder_id = f.id AND fc.deleted_at IS NULL AND f.deleted_at IS NULL
            WHERE f.id IN ({$bindings->getPlaceholder()})",
            $bindings->getParams()
        );
        while ([$folderId, $folderCode] = $query->fetch(PDO::FETCH_NUM)) {
            $folderCodesPerFolderId[$folderId][] = $folderCode;
        }
        return $folderCodesPerFolderId;
    }


    /**
     * Lazy getter
     *
     * @return External_ClassInterface
     */
    private function getExternalClass(): External_ClassInterface
    {
        if (!$this->externalClass) {
            $this->externalClass = new External_Class();
        }
        return $this->externalClass;
    }
}
