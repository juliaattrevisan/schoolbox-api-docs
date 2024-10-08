<?php

/**
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use PDO;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

/**
 * Class GroupMapper
 *
 * Used for mappings group types to categories
 *
 * @package Schoolbox\Resource
 * @method Group createModel($idValue = null)
 * @method Group hydrate(array $row, $alias = '', $callback = null)
 * @method Group findById($idValue)
 * @method Group[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class GroupMapper extends Mapper
{
    protected function mapping()
    {
        $this->hasModel(Group::class);
        $this->hasTable('userGroups');
        $this->uses('type', self::TYPE_INTEGER);
        $this->uses('force_follow', self::TYPE_BOOLEAN);
        $this->joins('cid', FolderMapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'folder', 'folder');

        $this->registerCallback(self::CALLBACK_POSTDELETE, [ $this, 'deleteGroupMemberships' ]);

        $this->registerPersistSearchIndexCallbacks();
    }


    /**
     * @inheritDoc
     */
    protected function getSelectString()
    {
        return "
            SELECT  {$this->getColumnString()},
                    {$this->getJoinColumnString('folder', 'f')}
        ";
    }


    /**
     * @inheritDoc
     */
    protected function getFromString()
    {
        return "
            FROM {$this->table}
            INNER JOIN {$this->getJoinSql('folder', '', 'f')}
        ";
    }


    /**
     * @inheritDoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');
        $groups       = $this->createCollection();
        foreach ($statement as $row) {
            $group = $this->hydrate($row)->setFolder($folderMapper->hydrate($row, 'f'));
            $groups[$group->getFolder()->getId()] = $group;
        }
        return $groups;
    }


    /**
     * Find all folders matching a certain name,
     * associated with userGroups
     *
     * @param string $name
     * @return GenericCollection|Folder\Model[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function findFolders($name = '')
    {
        $folderMapper = $this->getJoinMapper('folder');
        $allFolders = $folderMapper->createCollection();

        /* if $name is empty, the LIKE returns everything anyway
         */
        $query = "
            SELECT
                {$this->getColumnString('ug')},
                {$this->getJoinColumnString('folder', 'f')}
            FROM
                `{$this->getTable()}` ug
            LEFT JOIN
                {$this->getJoinSql('folder', 'ug', 'f')}
            WHERE f.id IS NOT NULL
            AND f.name LIKE :name
            ORDER BY f.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'name' => "%{$name}%"
        ]);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $allFolders->add($folderMapper->hydrate($row, 'f'));
        }

        return $allFolders;
    }

    /**
     * On deletion (a.k.a. disband) of a group: remove all memberships to that
     * group.
     *
     * This in turn triggers a recalculation of user subscriptions.
     *
     * @param Group $group
     */
    public function deleteGroupMemberships(Group $group): void
    {
        UserAccessMapper::getInstance()->deleteByColumnList([
            'sid'     => $group->getFolder()->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
        ]);
    }

    /**
     * get all available groups ids in the system that the user could enrol in
     * based on the user requested, having at least read perms to that folder
     */
    public function getAvailableGroupIds(User $user): array
    {
        //get list of all possible public groups, that the role has at least read permissions for.
        $groups = $this->database->prepare("
        SELECT userGroups.cid
            FROM userGroups
            WHERE userGroups.type in (:mod, :free)
            AND userGroups.cid in (SELECT perms.cid FROM perms where perms.gid = :roleID and perms.type != :deny)
            ");
        $groups->execute(array(
                             'roleID'   => $user->getRole()->getId(),
                             'deny'     => UserAccess::ACCESS_DENY,
                             'mod'      => Group::MODERATED_GROUP,
                             'free'     => Group::FREE_GROUP,
                         ));
        $groupsArr = [];
        while ($groupsLine = $groups->fetch()) {
            $groupsArr[] = (int)$groupsLine['cid'];
        }
        return $groupsArr;
    }
}
