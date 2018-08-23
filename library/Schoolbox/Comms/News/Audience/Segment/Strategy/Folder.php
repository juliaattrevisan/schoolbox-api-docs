<?php
/**
 * Folder
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;
use Schoolbox\Comms\News\Audience\Segment\ValidRestrictedSearch;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

class Folder implements ISearch, ITranslate
{

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


    /**
     * Folder constructor.
     * @param User             $user
     * @param FolderMapper     $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     */
    public function __construct(User $user, FolderMapper $folderMapper, RoleAccessMapper $roleAccessMapper)
    {
        $this->user = $user;
        $this->folderMapper = $folderMapper;
        $this->roleAccessMapper = $roleAccessMapper;
    }


    /**
     * Returns the set of folder ids where $user has READ+ access due to role permissions
     *
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function findFolderIdsWithRoleAccess(): array
    {
        $pb = $this->roleAccessMapper->getPredicateBuilder()
                                     ->equals('gid', $this->user->getRole()->getId())
                                     ->greaterThanOrEqualTo('type', UserAccess::ACCESS_READ);

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
     * @return string
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getSearchSql(): string
    {
        $folderMembershipSql = '';

        if ((new ValidRestrictedSearch())->isSatisfiedBy($this->user)) {

            /* Check to see whether I have READ+ access to any group/class folders
             */
            $groupAndClassMembership = $this->folderMapper->findFolderIdsForUser($this->user);

            /* Also check to see whether I have READ+ access in other ways
             * First, check role permissions on a group
             */
            $roleAccess = $this->findFolderIdsWithRoleAccess();

            /* Combine the two
             */
            $folderMembership = array_unique(array_merge($groupAndClassMembership, $roleAccess));

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

        $entityType = Model::ENTITY_TYPE_FOLDER;

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
            {$folderMembershipSql}
            AND (folder.name LIKE :keyword OR folder_code.code LIKE :keyword)
            AND folder.deleted_at IS NULL
            GROUP BY folder.id
            ORDER BY folder.name
        ";
    }


    /**
     * Translate this ITranslate instance from an entity_type to a human-readable version
     *
     * @return string
     */
    public function getTranslateSql(): string
    {
        return "
            SELECT cna1.*, f.name 
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
                WHERE entity_type = '".Model::ENTITY_TYPE_FOLDER."'
                AND cna2.comms_news_article_id = :articleId)
            AND folder.deleted_at IS NULL
            GROUP BY folder.id) AS f 
            INNER JOIN comms_news_audience cna1 ON cna1.entity_value = f.id AND entity_type = '".Model::ENTITY_TYPE_FOLDER."'
        ";
    }
}
