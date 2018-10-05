<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;


class Folder extends SimpleStrategy
{

    /** @var FolderMapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var Access */
    private $access;


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
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function getValidRelationshipModifiers(): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param bool             $staffCanPost
     * @param bool             $moderationRequired
     * @param FolderMapper     $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param Access           $access
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        bool $staffCanPost,
        bool $moderationRequired,
        FolderMapper $folderMapper,
        RoleAccessMapper $roleAccessMapper,
        Access $access
    ) {
        parent::__construct($userAccessMapper, $staffCanPost, $moderationRequired);
        $this->folderMapper     = $folderMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->access           = $access;
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
        return $this->access->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_WRITE;
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
        return $this->getUserAccessMapper()->getAccessLevel($user, null, UserAccess::SERVICE_CMS) >= $level;
    }


    /**
     * @inheritdoc
     * @throws InvalidColumnException
     * @throws Mapper\DefinitionException
     */
    public function getSearchSql(User $user, string $searchPlaceholder = null): string
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
                return '';
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
                '{$entityType}' AS entity_type
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE (
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
        // Note we don't care about teacher/parent modifier. The user must still have READ perm to the page
        return $this->access->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_READ;
    }

    /**
     * @@inheritdoc
     */
    public function canModerate(User $user, ?string $entityValue): bool
    {
        // Special case for folders
        return
            $user->isSuperuser() ||
            $this->hasCmsServicePerm($user, UserAccess::ACCESS_FULL) ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_FULL) ||
            (null !== $entityValue && $this->access->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_WRITE);
    }


    /**
     * Returns whether the specified user is allowed to audience to any folder (include NULL/no folder)
     *
     * @param User $user
     * @return bool
     */
    private function canAudienceToAnyClassOrGroup(User $user): bool
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
