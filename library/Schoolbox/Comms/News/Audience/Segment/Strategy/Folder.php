<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
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


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = self::getEntityType();
        return "
            SELECT cna1.entity_type, cna1.entity_value, f.name
            FROM 
            (SELECT folder.id,
                CONCAT(name,  
                CASE
                WHEN COUNT(code) > 0
                THEN CONCAT(' (', GROUP_CONCAT(code SEPARATOR ', '), ')')
                ELSE ''
                END) AS name
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE folder.id IN 
                (SELECT DISTINCT entity_value
                FROM comms_news_audience cna2
                WHERE entity_type = '{$entityType}'
                AND cna2.id IN ($segmentIdsPlaceholder))
            AND folder.deleted_at IS NULL
            GROUP BY folder.id) AS f 
            INNER JOIN comms_news_audience cna1 ON cna1.entity_value = f.id AND entity_type = '{$entityType}'
        ";
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
            $folderMembership = \array_unique(\array_merge($groupAndClassMembership, $roleAccess));

            /* If I have zero elements in my folderMembership, boot me out - there's nothing to fetch
             * I'm restricted from seeing any folders
             */
            if (!\count($folderMembership)) {
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
        return
            $user->isSuperuser() ||
            $this->hasCmsServicePerm($user, UserAccess::ACCESS_WRITE) ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE) ||
            (null !== $entityValue && $this->getAccess()->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_WRITE);
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
            $subjectLists = \array_merge($subjectLists, $subjectList);
        }

        $classFolderIds = $this->folderMapper->findAllBySubjectList($subjectLists)->getPropertyValues('id');

        return \array_unique(\array_merge($groupFolderIds, $classFolderIds));
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
}
