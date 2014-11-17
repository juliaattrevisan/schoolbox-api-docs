<?php
/**
 * EventRelations
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;

use Schoolbox\Calendar\Relation\Personal;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Calendar\Relation\RoleMapper;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\CategoryMapper;


class EventRelations {

    /**
     * An event object, sometimes one of many in a series
     *
     * @var null
     */
    private $event = null;

    /**
     * Access level, an int
     *
     * @var int
     */
    private $level = -1;

    /**
     * From $_POST
     *
     * @var array
     */
    private $group = array();

    /**
     * From $_POST
     *
     * @var array
     */
    private $roles = array();

    /**
     * An instance of PersonalMapper
     *
     * @var Relation\PersonalMapper
     */
    private $personalMapper = null;

    /**
     * An instance of CategoryMapper
     *
     * @var Relation\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * An instance of RoleMapper
     *
     * @var Relation\RoleMapper
     */
    private $roleMapper = null;

    /**
     * Constructor
     *
     * @param $level
     * @param $group
     * @param $roles
     */
    public function __construct($level, $group, $roles) {
        $this->setLevel($level);
        $this->setGroup($group);
        $this->setRoles($roles);

        /* Spawn mappers only once while iterating in series
         */
        $this->setPersonalMapper(new PersonalMapper());
        $this->setCategoryMapper(new CategoryMapper());
        $this->setRoleMapper(new RoleMapper());
    }

    /**
     * Reset and reassign relations
     */
    public function resetRelations($event) {
        $this->setEvent($event);

        /* Purge existing sids, insert new ones
         */
        $this->getPersonalMapper()->cleanse($this->getEvent());
        $this->getCategoryMapper()->cleanse($this->getEvent());
        $this->getRoleMapper()->cleanse($this->getEvent());

        /* Save new relations based on its access level
         */
        switch ($this->getLevel()) {
            /* Access Type 1 (Personal)
             */
            case 1:
                $userRelation = new Personal();
                $userRelation->setEventId($this->getEvent()->getId());
                $userRelation->setUserId($this->getEvent()->getOwner()->getId());
                $this->getPersonalMapper()->save($userRelation);
                break;

            /* Access Type 2 (Category)
             */
            case 2:
                foreach ($this->getGroup() as $currentGroup) {
                    $categoryRelation = new Category();
                    $categoryRelation->setEventId($this->getEvent()->getId());
                    $categoryRelation->setCategoryId($currentGroup);
                    $this->getCategoryMapper()->save($categoryRelation);
                }
                break;

            /* Access Type 3 (Campus)
             */
            case 3:
                foreach ($this->getRoles() as $currentRole) {
                    $roleRelation = new Role();
                    $roleRelation->setEventId($this->getEvent()->getId());
                    $roleRelation->setRoleId($currentRole);
                    $this->getRoleMapper()->save($roleRelation);
                }
                break;

            default:
                break;
        }
    }

    /* Getters/Setters
     */

    /**
     * Set event
     *
     * @param null $event
     */
    private function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     * Get event
     *
     * @return null
     */
    private function getEvent()
    {
        return $this->event;
    }

    /**
     * Set group
     *
     * @param array $group
     */
    private function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * Get group
     *
     * @return array
     */
    private function getGroup()
    {
        return $this->group;
    }

    /**
     * Set level
     *
     * @param int $level
     */
    private function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * Get level
     *
     * @return int
     */
    private function getLevel()
    {
        return $this->level;
    }

    /**
     * Set roles
     *
     * @param array $roles
     */
    private function setRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * Get roles
     *
     * @return array
     */
    private function getRoles()
    {
        return $this->roles;
    }

    /**
     * Set CategoryMapper
     *
     * @param \Schoolbox\Calendar\Relation\CategoryMapper $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get CategoryMapper
     *
     * @return \Schoolbox\Calendar\Relation\CategoryMapper
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * Set PersonalMapper
     *
     * @param \Schoolbox\Calendar\Relation\PersonalMapper $personalMapper
     */
    private function setPersonalMapper($personalMapper)
    {
        $this->personalMapper = $personalMapper;
    }

    /**
     * Get PersonalMapper
     *
     * @return \Schoolbox\Calendar\Relation\PersonalMapper
     */
    private function getPersonalMapper()
    {
        return $this->personalMapper;
    }

    /**
     * Set RoleMapper
     *
     * @param \Schoolbox\Calendar\Relation\RoleMapper $roleMapper
     */
    private function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * Get RoleMapper
     *
     * @return \Schoolbox\Calendar\Relation\RoleMapper
     */
    private function getRoleMapper()
    {
        return $this->roleMapper;
    }
}