<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;

/**
 * @method GenericCollection|RoleAccess[] findByColumn($columnName, $columnValue, $options = [])
 */
class RoleAccessMapper extends Mapper
{

    /**
     * Cached permissions
     * Nested array structure $cachedPermissions[role][cid]
     * @var RoleAccess[][]
     */
    private $cachedPermissions = [];

    /**
    * @return void
    */
    protected function mapping()
    {
        $this->hasTable('perms');
        $this->hasModel(RoleAccess::class);

        $this->joins('cid', Folder\Mapper::class, 'id', self::TYPE_MODEL, null, 'category', 'category');
        $this->joins('gid', RoleMapper::class, 'id', self::TYPE_MODEL, null, 'role', 'role');
        $this->uses('type', self::TYPE_INTEGER);
    }

    /**
     * Gets all role permissions for $category.
     *
     * @param Folder\Model $category
     * @return Collection|RoleAccess[]
     * @throws Mapper\Exception
     */
    public function getGroupPermissions(Folder\Model $category)
    {
        return $this->findAllForFolder($category);
    }

    /**
     * @param Folder\Model $category
     * @return Collection|RoleAccess[]
     * @throws Mapper\Exception
     * @throws Mapper\DefinitionException If unable to locate a named join mapper
     */
    public function findAllForFolder(Folder\Model $category)
    {
        $roleMapper = $this->getJoinMapper('role');

        $builder = $this->getPredicateBuilder();
        $builder->equals('ra.cid', $category->getId());
        $builder->isNull('r.deleted_at');
        $whereClause = implode(' AND ', $builder->getWhere());

        // Get all permissions for the specified folder
        $query = "
            SELECT
                {$this->getColumnString('ra')},
                {$this->getJoinColumnString('role', 'r')}
            FROM {$this->getTable()} `ra`
            JOIN `{$roleMapper->getTable()}` `r` ON r.id = ra.gid
            WHERE {$whereClause}
            ORDER BY r.id
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($builder->getParams());

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $roleAccess = $this->hydrate($row, 'ra');
            $role = $roleMapper->hydrate($row, 'r');
            $roleAccess->setRole($role);
            $collection->add($roleAccess);
        }
        return $collection;
    }

    /**
    * Save category permissions, optionally recursively
    *
    * @param Folder\Model $category
    * @param int[] $perms - assoc array of role IDs and permission levels
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
    * @param Folder\Model $category
    * @param int[] $perms Maps role to permission level
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
            $children = Folder\Mapper::getInstance()->findByParent($category->getId());
            if ($children->count()) {
                foreach ($children as $subCategory) {
                    // recursive call
                    $c = $c->merge($this->generatePermissions($subCategory, $perms, $recursive));
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
     * @param int  $folderId
     * @param bool $cache
     * @return RoleAccess
     * @throws Mapper\Exception
     */
    public function getAccess(User $user, $folderId, $cache = true)
    {
        // let's avoid fatal errors in the face of unexpected failure
        if (!$user->getRole() instanceof Role) {
            $ra = new RoleAccess();
            $ra->setType(UserAccess::ACCESS_DENY);
            return $ra;
        }

        // Ensure we take role-permission from NNIAN
        if ($folderId != 0)
        {
            $folderMapper = Folder\Mapper::getInstance();
            $folder = $folderMapper->findCheaplyById($folderId, ['deleted' => true]);

            if ($folder->getInherit())
            {
                // Folder inherits, find its NNIAN
                $permissionFolder = $folderMapper->findNearestNonInheritingAncestralNode($folder);
                $folderId         = $permissionFolder->getId();
            }
        }

        // Setup cache array for this user's role and folder if it doesn't already exist)

        if (!isset($this->cachedPermissions[$user->getRole()->getId()])) {
            $this->cachedPermissions[$user->getRole()->getId()] = [];
        }


        // Permission check #1 - Cached results

        if ($cache && isset($this->cachedPermissions[$user->getRole()->getId()][$folderId])) {
            // Found cached result, return it
            return $this->cachedPermissions[$user->getRole()->getId()][$folderId];
        }

        /** @var RoleAccess $roleAccess */
        $roleAccess = $this->findOneByColumnList([
            'cid' => $folderId,
            'gid' => $user->getRole()->getId(),
        ]);
        if($cache) {
            $this->cachedPermissions[$user->getRole()->getId()][$folderId] = $roleAccess;
        }
        return $roleAccess;
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
