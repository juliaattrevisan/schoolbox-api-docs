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
use PDO;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use External_UserInterface;
use UnderflowException;
use function array_merge;
use function count;
use function implode;
use function in_array;

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
        $query = '
            SELECT cid
            FROM perms
            WHERE gid = ' . $user->getRole()->getId() . '
            AND type > ' . UserAccess::ACCESS_DENY;

        return $query;
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

        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT
                '{$entityType}' entity_type,
                folder.id entity_value,
                CONCAT(name, IF(COUNT(code) > 0, CONCAT(' (', GROUP_CONCAT(code SEPARATOR ', '), ')'), '')) name
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE {$whereClause}
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
        // Note we don't care about teacher/parent modifier. The user must still have READ perm to the page
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
        } /** @noinspection BadExceptionsProcessingInspection */
        catch (Mapper\Exception\Deleted|Mapper\Exception\NotFound $e) {
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


    /** @inheritDoc */
    public function getUserResolveSql(string $userTableAlias, string $paramPrefix, array $entityValues): UserResolutionSql
    {
        $targetNull = self::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $filtered   = $this->filterOutNullValues($entityValues);
        if (!$targetNull && !count($filtered)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        // For performance reasons, it is faster to UNION the GROUP + CLASS members, rather than LEFT JOIN them.
        // This is incompatible with the interface's return type. Therefore we will just resolve the user ids,
        // and return a predicate to match these directly. In any event, the overhead of fetching the class members
        // from the sis is much higher than this double tap.
        $userIds = $this->getUserIdsForFolders($filtered, $targetNull);
        if (!count($userIds)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        return new UserResolutionSql('', $this->newPb($paramPrefix)->in("$userTableAlias.id", $userIds));
    }


    /**
     * @param string[] $filtered
     * @param bool     $targetNull
     * @return int[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getUserIdsForFolders(array $filtered, bool $targetNull): array
    {
        // Get group users, regardless of whether they have read/write/mod perms. (but not deny)
        $quoter    = new Quoter();
        $groupJoin = $targetNull ? 'LEFT' : '';
        $groupPb   = (new Builder($quoter, ''));
        if (count($filtered)) {
            $groupInPb = (new Builder($quoter, ''))
                ->equals('ua.service', UserAccess::SERVICE_HOMEPAGE)
                ->in('ua.sid', $filtered)
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
        $groupWhere = implode(' AND ', $groupPb->getWhere());
        $sql        = "
            SELECT ua.uid id
            FROM user u
            {$groupJoin} JOIN userAccess ua ON u.id = ua.uid
            WHERE ({$groupWhere})
            AND u.deleted_at IS NULL
        ";
        $params     = $groupPb->getParams();

        // Associated with the class, regardless of role type.
        // For students/teachers you'd need to use a composite segment that intersects the desired roleType.
        if (count($filtered)) {
            $codes = $this->getFolderCodes($filtered);

            if (count($codes)) {
                $externalIds = [];
                foreach ($this->getExternalClass()->getStudents(implode(',', $codes)) as $student) {
                    $externalIds[] = $student['synergy_id'];
                }
                foreach ($this->getExternalClass()->getTeachers(implode(',', $codes)) as $teacherExternalId) {
                    $externalIds[] = $teacherExternalId;
                }
                if (count($externalIds)) {
                    $classPb = (new Builder($quoter, ''))
                        ->in('u.synergy_id', $externalIds);
                    $sql     .= " UNION SELECT u.id FROM user u WHERE {$classPb->getWhere()[0]} AND u.deleted_at IS NULL";
                    $params  = array_merge($params, $classPb->getParams());
                }
            }
        }

        return $this->folderMapper->getDatabase()->query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Returns all folder codes for the specified folders
     *
     * @param string[] $folderIds
     * @return string[]
     */
    private function getFolderCodes(array $folderIds): array
    {
        if (!count($folderIds)) {
            return [];
        }

        $bindings = Builder::getBindings($folderIds, 'fid');
        return $this->folderMapper->getDatabase()->query("
            SELECT code
            FROM folder_code fc
            JOIN folder f ON fc.folder_id = f.id AND fc.deleted_at IS NULL AND f.deleted_at IS NULL
            WHERE f.id IN ({$bindings->getPlaceholder()})",
            $bindings->getParams()
        )->fetchAll(PDO::FETCH_COLUMN);
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
