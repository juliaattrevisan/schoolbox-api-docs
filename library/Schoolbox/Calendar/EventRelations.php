<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use PDOException;
use Schoolbox\Calendar\Event\Attendance;
use Schoolbox\Calendar\Event\Attendance\Transactor;
use Schoolbox\Calendar\Relation\Personal;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Calendar\Relation\RoleMapper;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
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
     * This bool decides whether the attendance settings are "dirty" such that they need guests
     * to reconfirm their attendance. Events can be modified at any time which can result in
     * the clearing and re-adding of attendees who have already RSVP'd. To prevent their RSVP
     * setting from being lost, this bool is set when the datetime changes; changing the title
     * and description (for example) of an event shouldn't reset one's RSVP choices
     *
     * Note that as of SA-10697 guests no longer need to re-confirm their
     * attendance if an event's time changes; therefore, it's unlikely that
     * this flag will ever need to be set to true.
     *
     * @var bool
     */
    private $attendanceDirty;


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
     * @var Attendance\Mapper
     */
    private $attendanceMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Constructor
     *
     * @param int|null   $level
     * @param int[]|null $group
     * @param int[]|null $roles
     * @param int[]|null $guests
     * @param bool       $attendanceDirty
     */
    public function __construct($level, $group, $roles, $guests, bool $attendanceDirty)
    {
        $this->level = $level;
        $this->group = $group;
        $this->roles = $roles;
        $this->guests = $guests;
        $this->attendanceDirty = $attendanceDirty;

        /* Spawn mappers only once while iterating in series
         */
        $this->personalMapper = new PersonalMapper();
        $this->categoryMapper = new CategoryMapper();
        $this->roleMapper = new RoleMapper();
        $this->attendanceMapper = new Attendance\Mapper();
        $this->userMapper = new UserMapper();
    }


    /**
     * Reset and reassign relations
     *
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
                    $this->saveAttendanceRelation($event, $this->guests);
                    break;

                /* Access Type 2 (Category)
                 */
                case Event::ACCESS_GROUP:
                    $this->saveFolderRelation($event, $this->group);
                    $this->saveAttendanceRelation($event, $this->guests);
                    break;

                /* Access Type 3 (Campus)
                 */
                case Event::ACCESS_CAMPUS:
                    $this->saveRoleRelation($event, $this->roles);
                    $this->saveAttendanceRelation($event, $this->guests);
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
        $this->personalMapper->save((new Personal())
            ->setEventId($event->getId())
            ->setUserId($event->getOwner()->getId()));
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
            $this->categoryMapper->save((new Category())
                ->setEventId($event->getId())
                ->setCategoryId($currentGroup));
        }
    }


    /**
     * Save a specific role relation
     *
     * @param Event $event
     * @param array $roleIds
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function saveRoleRelation(Event $event, array $roleIds): void
    {
        foreach ($roleIds as $currentRole) {
            $this->roleMapper->save((new Role())
                ->setEventId($event->getId())
                ->setRoleId($currentRole));
        }
    }


    /**
     * Save a specific user relation
     *
     * @param Event $event
     * @param array $guestIds
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function saveAttendanceRelation(Event $event, array $guestIds): void
    {

        /* If attendances were turned off, purge everyone, then let's get out of here
         */
        if (!$event->getAttendance()) {
            $this->attendanceMapper->deleteByColumn('event_id', $event->getId());

            return;
        }

        /* Otherwise process the people who were added
         */
        if ($this->attendanceDirty) {
            /* If the attendanceDirty bit is set, reset attendance responses entirely
             */
            $this->attendanceMapper->deleteByColumn('event_id', $event->getId());

            foreach ($guestIds as $currentGuest) {
                $this->attendanceMapper->save((new Event\Attendance\Model())
                    ->setEvent($event)
                    ->setUser($this->userMapper->createModel()->setId($currentGuest))
                    ->setStatus(Event\Attendance\Model::STATUS_PENDING));
            }
        } else {
            (new Transactor($this->attendanceMapper, $this->userMapper))->update($event, $guestIds);
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
