<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Calendar;

use PDOException;
use Schoolbox\Calendar\Relation\Personal;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Calendar\Relation\RoleMapper;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class EventRelations
 *
 * Manage relational data for each event instance, such as roles/groups/guests
 *
 * This can be used iteratively to process data identically for multiple instances of events
 * such as recurring events
 *
 * @package Schoolbox\Calendar
 */
class EventRelations
{

    /**
     * Access level, an int
     *
     * @var int
     */
    private $level;


    /**
     * A set of Folder Id
     *
     * @var int[]
     */
    private $group;


    /**
     * A set of Role Id
     *
     * @var int[]
     */
    private $roles;


    /**
     * A set of User Id
     *
     * @var int[]
     */
    private $guests;


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
     * @param int[]|null $guests
     */
    public function __construct($level, $group, $roles, $guests)
    {
        $this->level= $level;
        $this->group = $group;
        $this->roles = $roles;
        $this->guests = $guests;

        /* Spawn mappers only once while iterating in series
         */
        $this->personalMapper = new PersonalMapper();
        $this->categoryMapper = new CategoryMapper();
        $this->roleMapper = new RoleMapper();
    }


    /**
     * Reset and reassign relations
     * @param Event $event
     * @throws PDOException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function resetRelations(Event $event): void
    {

        /* Save new relations based on its access level
         * Skip this entirely if no level is specified/detected
         */
        if (is_numeric($this->level)) {
            $this->purgeAllRelations([$event->getId()]);

            switch ($this->level) {
                /* Access Type 1 (Personal)
                 */
                case Event::ACCESS_PERSONAL:
                    $this->savePersonalRelation($event);
                    break;

                /* Access Type 2 (Category)
                 */
                case Event::ACCESS_GROUP:
                    $this->saveFolderRelation($event, $this->group);
                    break;

                /* Access Type 3 (Campus)
                 */
                case Event::ACCESS_CAMPUS:
                    foreach ($this->roles as $currentRole) {
                        $roleRelation = new Role();
                        $roleRelation->setEventId($event->getId());
                        $roleRelation->setRoleId($currentRole);
                        $this->roleMapper->save($roleRelation);
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
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function savePersonalRelation(Event $event): void
    {
        $userRelation = new Personal();
        $userRelation->setEventId($event->getId());
        $userRelation->setUserId($event->getOwner()->getId());
        $this->personalMapper->save($userRelation);
    }


    /**
     * Save a specific folder relation
     *
     * @param Event $event
     * @param array $folderIds
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function saveFolderRelation(Event $event, array $folderIds): void
    {
        foreach ($folderIds as $currentGroup) {
            $categoryRelation = new Category();
            $categoryRelation->setEventId($event->getId());
            $categoryRelation->setCategoryId($currentGroup);
            $this->categoryMapper->save($categoryRelation);
        }
    }


    /**
     * Purge existing sids, before inserting new ones
     *
     * @param array $eventIds
     */
    public function purgeAllRelations(array $eventIds): void
    {
        $this->personalMapper->deleteByColumn('event_id', $eventIds);
        $this->categoryMapper->deleteByColumn('event_id', $eventIds);
        $this->roleMapper->deleteByColumn('event_id', $eventIds);
    }
}
