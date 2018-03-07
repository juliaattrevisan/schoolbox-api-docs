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
     * @var Event
     */
    private $event;

    /**
     * Access level, an int
     *
     * @var int
     */
    private $level = -1;

    /**
     * From $_POST
     *
     * @var int[]
     */
    private $group = [];

    /**
     * From $_POST
     *
     * @var int[]
     */
    private $roles = [];

    /**
     * An instance of PersonalMapper
     *
     * @var Relation\PersonalMapper
     */
    private $personalMapper;

    /**
     * An instance of CategoryMapper
     *
     * @var Relation\CategoryMapper
     */
    private $categoryMapper;

    /**
     * An instance of RoleMapper
     *
     * @var Relation\RoleMapper
     */
    private $roleMapper;

    /**
     * Constructor
     *
     * @param int|null   $level
     * @param int[]|null $group
     * @param int[]|null $roles
     */
    public function __construct($level, $group, $roles)
    {
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
     * @param Event $event
     * @throws \PDOException
     */
    public function resetRelations($event)
    {
        $this->setEvent($event);

        /* Save new relations based on its access level
         * Skip this entirely if no level is specified/detected
         */
        if (is_numeric($this->getLevel())) {
            $this->purgeAllRelations([$event->getId()]);

            switch ($this->getLevel()) {
                /* Access Type 1 (Personal)
                 */
                case Event::ACCESS_PERSONAL:
                    $this->savePersonalRelation($this->getEvent());
                    break;

                /* Access Type 2 (Category)
                 */
                case Event::ACCESS_GROUP:
                    $this->saveFolderRelation($this->getEvent(), $this->getGroup());
                    break;

                /* Access Type 3 (Campus)
                 */
                case Event::ACCESS_CAMPUS:
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
    }


    /**
     * Save a specific personal relation
     *
     * @param Event $event
     * @throws \PDOException
     */
    public function savePersonalRelation(Event $event)
    {
        $userRelation = new Personal();
        $userRelation->setEventId($event->getId());
        $userRelation->setUserId($event->getOwner()->getId());
        $this->getPersonalMapper()->save($userRelation);
    }


    /**
     * Save a specific folder relation
     *
     * @param Event $event
     * @param array $folderIds
     * @throws \PDOException
     */
    public function saveFolderRelation(Event $event, $folderIds)
    {
        foreach ($folderIds as $currentGroup) {
            $categoryRelation = new Category();
            $categoryRelation->setEventId($event->getId());
            $categoryRelation->setCategoryId($currentGroup);
            $this->getCategoryMapper()->save($categoryRelation);
        }
    }


    /**
     * Purge existing sids, before inserting new ones
     * @param array $eventIds
     */
    public function purgeAllRelations($eventIds)
    {
        $this->getPersonalMapper()->deleteByColumn('event_id', $eventIds);
        $this->getCategoryMapper()->deleteByColumn('event_id', $eventIds);
        $this->getRoleMapper()->deleteByColumn('event_id', $eventIds);
    }

    /* Getters/Setters
     */

    /**
     * Set event
     *
     * @param Event $event
     * @return $this
     */
    private function setEvent($event)
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Get event
     *
     * @return Event
     */
    private function getEvent()
    {
        return $this->event;
    }

    /**
     * Set group
     *
     * @param int[] $group
     * @return $this
     */
    private function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * Get group
     *
     * @return int[]
     */
    private function getGroup()
    {
        return $this->group;
    }

    /**
     * Set level
     *
     * @param int $level
     * @return $this
     */
    private function setLevel($level)
    {
        $this->level = $level;
        return $this;
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
     * @param int[] $roles
     * @return $this
     */
    private function setRoles($roles)
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * Get roles
     *
     * @return int[]
     */
    private function getRoles()
    {
        return $this->roles;
    }

    /**
     * Set CategoryMapper
     *
     * @param \Schoolbox\Calendar\Relation\CategoryMapper $categoryMapper
     * @return $this
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
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
     * @return $this
     */
    private function setPersonalMapper($personalMapper)
    {
        $this->personalMapper = $personalMapper;
        return $this;
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
     * @return $this
     */
    private function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
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