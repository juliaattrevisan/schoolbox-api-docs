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
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;

class RoleAccessMapper extends Mapper
{

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
     * @param CategoryModel $category
     *
     * @return Collection|RoleAccess[]
     * @throws Mapper\Exception
     */
    public function getGroupPermissions(CategoryModel $category) 
    {
        return $this->findAllForFolder($category);
    }

    /**
     * @param CategoryModel $category
     * @return Collection|RoleAccess[]
     * @throws Mapper\Exception
     */
    public function findAllForFolder(CategoryModel $category)
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
    * @param CategoryModel $category
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
    * @param CategoryModel $category
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
            $children = CategoryMapper::getInstance()->findByParent($category->getId());
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
    public function getAccess(User $user, $serviceIdentifier, $service = UserAccess::SERVICE_HOMEPAGE)
    {
        // let's avoid fatal errors in the face of unexpected failure
        if (!$user->getRole() instanceof Role) {
            $ra = new RoleAccess();
            $ra->setType(UserAccess::ACCESS_DENY);
            return $ra;
        }
        return $this->findOneByColumnList(
            array(
                'cid' => $serviceIdentifier,
                'gid' => $user->getRole()->getId()
            )
        );
    }

}
