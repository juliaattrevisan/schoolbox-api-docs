<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\ValidRestrictedSearch;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Comms\News\Audience;
use Schoolbox\User;

/**
 * @TODO due to disparate dependencies, this should probably be a composite of classes each capable of a subset of interface methods.
 * @see https://github.com/alaress/schoolbox/pull/16840#discussion-diff-210142468
 */
class Folder extends Base
{
    public const ENTITY_TYPE = 'folder';

    /**
     * @var User
     */
    private $user;


    /**
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * @var RoleAccessMapper
     */
    private $roleAccessMapper;

    /** @var bool */
    private $moderationEnabled;

    /** @var Access */
    private $access;


    /**
     * Folder constructor.
     *
     * @param User             $user
     * @param FolderMapper     $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $moderationEnabled
     */
    public function __construct(User $user, FolderMapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $moderationEnabled)
    {
        $this->user              = $user;
        $this->folderMapper      = $folderMapper;
        $this->roleAccessMapper  = $roleAccessMapper;
        $this->moderationEnabled = $moderationEnabled;
        $this->access            = Access::getInstance(); // TODO inject this
    }


    /**
     * @inheritdoc
     */
    public function canBeTargetedByUser(User $user): bool
    {
        return true;
    }


    /**
     * Returns the set of folder ids where $user has the specified access or greater due to role permissions
     *
     * @param int $accessLevel
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function findFolderIdsWithRoleAccess(int $accessLevel): array
    {
        $pb = $this->roleAccessMapper->getPredicateBuilder()
                                     ->equals('gid', $this->user->getRole()->getId())
                                     ->greaterThanOrEqualTo('type', $accessLevel);

        return $this->roleAccessMapper->findScalarByPredicates('cid', $pb);
    }


    /**
     * Gets an sql fragment for the ISearch instance
     *
     * To use a Folder as an audiencing target,
     *    - Folders must have a class code or be a Group
     *    - Folder can't be deleted
     *
     * Anything else is rolled over or otherwise inaccessible and shouldn't appear as a search result
     * The result set is affected by whether $user has READ access to the folder in some way
     *
     *    - Superusers/Comms Officers/CMS Perms aren't limited to what they can search and can audience
     *    - Restrict non-superusers/non-comms-officer/non-CMS Perms people
     *
     * If the resultant list of folders is empty, exit early and don't bother executing query
     *
     * @param string $searchPlaceholder
     * @return string
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $folderMembershipSql = '';

        if ((new ValidRestrictedSearch())->isSatisfiedBy($this->user)) {

            // What access level a user must have depends on whether moderation is enabled
            $minAccessLevel = $this->moderationEnabled ? UserAccess::ACCESS_READ : UserAccess::ACCESS_WRITE;

            // Get folders where user has required access via group/class individual permissions
            $groupAndClassMembership = $this->folderMapper->findFolderIdsForUser($this->user, true, $minAccessLevel);

            // Get folders where user has required access via their role permissions
            $roleAccess = $this->findFolderIdsWithRoleAccess($minAccessLevel);

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
                '{$this->getEntityType()}' AS entity_type
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
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
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
                WHERE entity_type = '{$this->getEntityType()}'
                AND cna2.id IN ($segmentIdsPlaceholder))
            AND folder.deleted_at IS NULL
            GROUP BY folder.id) AS f 
            INNER JOIN comms_news_audience cna1 ON cna1.entity_value = f.id AND entity_type = '{$this->getEntityType()}'
        ";
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // Quite possible for a user to not have any folders
        return true;
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(User $user): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }


    /**
     * @inheritdoc
     */
    public function canViewEntityValue(User $user, ?string $entityValue): bool
    {
        return $this->access->getFolderAccess($user, (int)$entityValue) >= UserAccess::ACCESS_READ;
    }
}
