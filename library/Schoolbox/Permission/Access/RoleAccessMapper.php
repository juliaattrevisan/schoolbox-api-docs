<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;

class RoleAccessMapper extends Mapper
{

    /**
     * Cached permissions
     * Nested array structure $cachedPermissions[role][cid]
     * @var array
     */
    protected $cachedPermissions = [];

    /**
    * @return void
    */
    protected function mapping()
    {

        $this->hasTable('perms');
        $this->hasModel('\\Schoolbox\\Permission\\Access\\RoleAccess');

        $this->hasMap(array(
            'column'     => 'cid',
            'property'   => 'category',
            'type'       => self::TYPE_MODEL,
            'join'       => 'category'
        ));

        $this->hasMap(array(
            'column'     => 'gid',
            'property'   => 'role',
            'type'       => self::TYPE_MODEL,
            'join'       => 'role'
        ));

        $this->hasMap(array(
            'column'     => 'type',
            'property'   => 'type',
            'type'       => self::TYPE_INTEGER
        ));

        $this->hasJoin('category', array(
            'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
            'this_key'  => 'cid',
            'other_key' => 'id'
        ));

        $this->hasJoin('role', array(
            'mapper'    => '\\Schoolbox\\RoleMapper',
            'this_key'  => 'gid',
            'other_key' => 'id'
        ));

    }

    /**
     * Gets all role permissions for $category.
     *
     * @param FolderModel $category
     *
     * @return Collection|RoleAccess[]
     * @throws Mapper\Exception
     */
    public function getGroupPermissions(FolderModel $category)
    {
        return $this->findAllForFolder($category);
    }

    /**
     * @param FolderModel $category
     * @return Collection|RoleAccess[]
     * @throws Mapper\Exception
     */
    public function findAllForFolder(FolderModel $category)
    {
        // Get permissions for all roles, regardless of whether there's one in
        // the database for this category.
        $query = "
            SELECT
                {$this->getColumnString('ra')},
                {$this->getJoinColumnString('role', 'r')}
            FROM `{$this->getJoinMapper('role')->getTable()}` `r`
            LEFT JOIN {$this->getTable()} `ra` ON r.id=ra.gid AND ra.cid=:categoryId
            ORDER BY r.id
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array(
            'categoryId' => $category->getId()
        ));

        $collection = new Collection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $roleAccess = $this->hydrate($row, 'ra');
            $role = $this->getJoinMapper('role')->hydrate($row, 'r');
            $roleAccess->setRole($role);
            $collection->add($roleAccess);
        }

        return $collection;
    }

    /**
    * Save category permissions, optionally recursively
    *
    * @param FolderModel $category
    * @param array $perms - assoc array of role IDs and permission levels
    * @param bool $recursive
    * @return bool
    */
    public function savePermissions($category, $perms, $recursive = false)
    {
        if (!(is_array($perms) && count($perms))) {
            return false;
        }

        $accessCollection = $this->generatePermissions($category, $perms, $recursive);
        $success = $this->save($accessCollection);

        return !!(is_array($success) ? count($success) : $success);
    }


    /**
    * Recursive helper function for generating a collection of permissions
    *
    * @param FolderModel $category
    * @param $perms
    * @param bool $recursive
    * @return Collection|RoleAccess[]
    */
    protected function generatePermissions($category, $perms, $recursive = false)
    {
        $c = new Collection();

        foreach ($perms as $roleId => $permissionLevel) {
            // add this category to the collection
            $c->add(
                $this->createModel()
                    ->setCategory($category)
                    ->setRole(RoleMapper::getInstance()->createModel()->setId($roleId))
                    ->setType($permissionLevel)
            );
        }

        // if recursing, locate children and generate permissions for them as well
        if ($recursive) {
            $children = FolderMapper::getInstance()->findByParent($category->getId());
            if ($children->count()) {
                foreach ($children as $subCategory) {
                    // recursive call
                    $c->merge($this->generatePermissions($subCategory, $perms, $recursive));
                }
            }
        }

        return $c;
    }


    /**
     * This model does not have any notifications
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        // noop
        return false;
    }


    /**
     * @param User $user
     * @param int  $serviceIdentifier
     * @param int  $service
     * @return RoleAccess
     */
    public function getAccess(User $user, $serviceIdentifier, $service = UserAccess::SERVICE_HOMEPAGE, $cache = true)
    {
        // let's avoid fatal errors in the face of unexpected failure
        if (!$user->getRole() instanceof Role) {
            $ra = new RoleAccess();
            $ra->setType(UserAccess::ACCESS_DENY);
            return $ra;
        }

        if ($serviceIdentifier != 0)
        {
            $folder = FolderMapper::getInstance()->findCheaplyById($serviceIdentifier, ['deleted' => true]);

            if ($folder->getInherit())
            {
                // Folder inherits, find its NNIAN
                $permissionFolder = FolderMapper::getInstance()->findNearestNonInheritingAncestralNode(
                    $folder
                );
                $serviceIdentifier = $permissionFolder->getId();
            }
        }

        // Setup cache array for this user/service/sid if it doesn't already exist)

        if (!isset($this->cachedPermissions[$user->getRole()->getId()])) {
            $this->cachedPermissions[$user->getRole()->getId()] = [];
        }


        // Permission check #1 - Cached results

        if (isset($this->cachedPermissions[$user->getRole()->getId()][$serviceIdentifier]) && $cache) {
            // Found cached result, return it
            return $this->cachedPermissions[$user->getRole()->getId()][$serviceIdentifier];
        }


        $result =  $this->findOneByColumnList(
            array(
                'cid' => $serviceIdentifier,
                'gid' => $user->getRole()->getId()
            )
        );
        if($cache) {
            $this->cachedPermissions[$user->getRole()->getId()][$serviceIdentifier] = $result;
        }
        return $result;
    }


    /**
     * Determine whether or not the give role is allowed to own folders
     *
     * @param Role $role
     * @return bool
     */
    public function roleMayOwnFolders(Role $role)
    {
        $roleType = $role->getType();

        if (!$roleType) {
            // do not allow false positives in the event of poorly-hydrated object
            return false;
        }

        return $this->getConfigLoader()->get('group_staff_only') ? ($roleType == Role::STAFF) : true;

    }
}
