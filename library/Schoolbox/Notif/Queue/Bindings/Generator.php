<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Bindings;


use External_User;
use External_UserInterface;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Generator implements GeneratorInterface
{

    /** @var \External_UserInterface */
    protected $xUserHandler;

    /** @var \Schoolbox\Mapper */
    protected $userMapper;

    /** @var \Schoolbox\Mapper */
    protected $roleMapper;

    /** @var \Schoolbox\Mapper */
    protected $categoryMapper;

    /** @var \Schoolbox\Mapper */
    protected $userAccessMapper;

    /** @var array */
    protected $roleCache;


    /**
     * @param External_UserInterface $xUserHandler
     * @param Mapper                 $userMapper
     * @param Mapper                 $roleMapper
     * @param Mapper                 $userAccessMapper
     * @param CategoryMapper         $categoryMapper
     */
    public function __construct(
        External_UserInterface $xUserHandler = null,
        Mapper $userMapper = null,
        Mapper $roleMapper = null,
        Mapper $userAccessMapper = null,
        CategoryMapper $categoryMapper = null
    ) {
        $this->setExternalUserHandler($xUserHandler)
             ->setUserMapper($userMapper)
             ->setRoleMapper($roleMapper)
             ->setUserAccessMapper($userMapper)
             ->setCategoryMapper($categoryMapper);
    }


    /**
     * @return External_UserInterface
     */
    public function getExternalUserHandler()
    {
        if (!$this->xUserHandler) {
            $this->setExternalUserHandler(new External_User());
        }

        return $this->xUserHandler;
    }


    /**
     * @param External_UserInterface $externalUserHandler
     * @return self
     */
    public function setExternalUserHandler($externalUserHandler)
    {
        $this->xUserHandler = $externalUserHandler;
        return $this;
    }


    /**
     * @param Mapper $userAccessMapper
     * @return self
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        if (!$this->userAccessMapper) {
            $this->setUserAccessMapper(UserAccessMapper::getInstance());
        }
        return $this->userAccessMapper;
    }


    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        if (!$this->roleMapper) {
            $this->setRoleMapper(RoleMapper::getInstance());
        }
        return $this->roleMapper;
    }


    /**
     * @param Mapper $roleMapper
     * @return self
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }


    /**
     * @param Mapper $categoryMapper
     * @return self
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }


    /**
     * @return CategoryMapper
     */
    public function getCategoryMapper()
    {
        if (!$this->categoryMapper) {
            $this->setCategoryMapper(CategoryMapper::getInstance());
        }
        return $this->categoryMapper;
    }


    /**
     * @param Mapper $userMapper
     * @return self
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @return \Schoolbox\UserMapper
     */
    public function getUserMapper()
    {
        if (!$this->userMapper) {
            $this->setUserMapper(UserMapper::getInstance());
        }
        return $this->userMapper;
    }


    /**
     * @param User $user
     * @return array
     */
    public function generateBindingsForUser(User $user)
    {

        $bindings = array(
            // required user information
            'user' => array(
                // *.notify.user.{id}.#
                'id' => $user->getId()
            )
        );

        $roleType = $user->getRole()->getType();

        // superuser bindings
        if ($user->isSuperuser()) {
            // *.notify.superuser.#
            $bindings['superuser'] = array('#');
        }

        // campus bindings
        foreach ($user->getCampusCollection() as $campus) {
            // *.notify.campus.{id}.#
            /** @var Campus $campus */
            $bindings['campus'][] = $campus->getId() . ".#";
        }

        // house bindings
        if ($house = $user->getSchoolHouse()->getId()) {
            // *.notify.house.{id}.#
            $bindings['house'] = array(
                "$house.#"
            );
        }

        // residential house bindings
        if ($resHouse = $user->getResidentialHouse()->getId()) {
            // *.notify.reshouse.{id}.#
            $bindings['reshouse'] = array(
                "$resHouse.#"
            );
        }

        // parental bindings TODO can this be more lightweight
        if ($roleType == Role::PARENT && $user->getExternalId() > 0) {
            $childrenArray = $this->getExternalUserHandler()
                                  ->getEnrolledStudents($user->getExternalId());

            if (is_array($childrenArray)) {
                $childIds = array();
                foreach ($childrenArray as $childArray) {
                    $childIds[] = $childArray['RelatedID'];
                }
                $roles = $this->getRoleCache();
                if (array_key_exists(Role::STUDENT, $roles)) {
                    $childCollection = $this->getUserMapper()->findByColumnList(array(
                        'synergy_id' => $childIds,
                        'group_id'   => $roles[Role::STUDENT]
                    ));
                    // parent bindings
                    // *.notify.parent.{childId}.#
                    foreach ($childCollection->getColumnValues('id') as $id) {
                        $bindings['parent'][] = "$id.#";
                    }
                }
            }
        }

        // role bindings
        // *.notify.role.{id}.#
        $bindings['role'] = array(
            $user->getRole()->getId() . ".#"
        );

        // role-type bindings
        // *.notify.roletype.{name}.#
        $bindings['roletype'] = array("$roleType.#");

        // Bindings for services:
        // *.notify.service.{service}.{itemId}.{level}.#
        // Higher access levels get lower level bindings as well.
        $bindings['service'] = array();

        try {
            // retrieve all homepages the user has access to; pull homepage service access from synergy as well
            $catIds = $this->getCategoryMapper()
                           ->findForUser($user)
                           ->getColumnValues('id');

            /** @var UserAccess $access */
            $accessList = $this->getUserAccessMapper()->findByColumnList(
                array(
                    'uid'     => $user->getId(),
                    'service' => UserAccess::SERVICE_HOMEPAGE,
                    'sid'     => $catIds
                )
            );
        } catch (Mapper\Exception $e) {
            $accessList = new Collection();
        }

        // combine with all other services user has access to
        $accessList->mergeUnique($user->getAccessList());

        foreach ($user->getAccessList() as $access) {
            $baseBinding = $access->getService() . '.' . $access->getServiceIdentifier();
            $accessLevel = $access->getAccessLevel();
            if ($accessLevel >= UserAccess::ACCESS_FULL) {
                $bindings['service'][] = $baseBinding . '.' . UserAccess::ACCESS_FULL . '.#';
            }
            if ($accessLevel >= UserAccess::ACCESS_WRITE) {
                $bindings['service'][] = $baseBinding . '.' . UserAccess::ACCESS_WRITE . '.#';
            }
            if ($accessLevel >= UserAccess::ACCESS_READ) {
                $bindings['service'][] = $baseBinding . '.' . UserAccess::ACCESS_READ . '.#';
            }
        }

        return $bindings;
    }


    /**
     * @return array
     */
    protected function getRoleCache()
    {
        // we many not need to actually look at the role cache, so create it on demand
        if (empty($this->roleCache)) {
            // cache roles, indexed by type
            $roles = $this->getRoleMapper()
                          ->findAll()
                          ->groupBy('type')
                          ->getAll();
            foreach ($roles as $rType => $roleCollection) {
                /** @var $roleCollection Collection|Role[] */
                $this->roleCache[$rType] = $roleCollection->getColumnValues('id');
            }

        }
        return $this->roleCache;
    }

} 
