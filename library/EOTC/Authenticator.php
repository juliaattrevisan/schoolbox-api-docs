<?php
/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace EOTC;

use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

class Authenticator
{

    /**
     * @var EventPermissionMapper
     */
    private $permissionMapper;

    /**
     * @var ConfigMapper
     */
    private $configMapper;

    /**
     * Cache obtained permissions in associative array
     *
     * [ userId => [allPermissions]]
     *
     * @var array
     */
    private $allPermissions = [];

    /**
     * Authenticator constructor.
     *
     * @param \SitePage $page
     */
    public function __construct(\SitePage $page)
    {
        // Return 404 if EOTC is not enabled
        if (0 == ConfigLoader::getInstance()->get('eotc_enabled')) {
            $page->pageNotFound();
        }

        $this->setPermissionMapper(new EventPermissionMapper());
        $this->setConfigMapper(ConfigMapper::getInstance());
    }

    /**
     * Determine whether the specified user can spawn an ETOC event
     *
     * @param User $user
     * @return bool
     */
    public function isEOTCCreator(User $user)
    {
        if ($user->getRole()->isStaff() || $user->isSuperuser()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the specified user is an EOTC Administrator
     * (a staff user with at least one valid EOTC Permission assigned)
     *
     * Only EOTC Administrators can Promote event statuses
     * EOTC Administrator must have matching campus/status permissions
     *
     * Allow all EOTC Administrators to see Approved events
     * Allow all EOTC Administrators to see Rejected events
     *
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function isEOTCAdministrator(User $user, Event $event = null)
    {
        if ($user->isSuperuser()) {
            return true;
        }

        /* Cache db call outcome, since these calls can be used in
         * a loop, which really hits the db
         */
        if (!isset($this->allPermissions[$user->getId()])) {
            $this->allPermissions[$user->getId()] = $this->getPermissionMapper()->findByUser($user);
        }

        /* Check permissions against a particular event's campus and status
         */
        $allPermissions = $this->getAllPermissions();

        if ($event) {
            foreach ($allPermissions[$user->getId()] as $currentPermission) {
                $inCampus  = $this->isPermissionInCampus($currentPermission, $event);
                $hasStatus = $this->isPermissionInStatus($currentPermission, $event);

                if ($inCampus && $hasStatus) {
                    return true;
                }
            }
        }
        /* Determine whether you are an EOTC administrator, in general
		 */
        else {
            if (count($this->getAllPermissions())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the specified user is an EOTC Administrator
     * for the Campus associated with $event.
     *
     * This will not check Status perms.
     *
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function isEOTCAdministratorForCampus(User $user, Event $event)
    {
        /* Cache db call outcome, since these calls can be used in
         * a loop, which really hits the db
         */
        if (!isset($this->allPermissions[$user->getId()])) {
            $this->allPermissions[$user->getId()] = $this->getPermissionMapper()->findByUser($user);
        }

        /* Check permissions against a particular event's campus only
         */
        $allPermissions = $this->getAllPermissions();

        foreach ($allPermissions[$user->getId()] as $currentPermission) {
            $inCampus = $this->isPermissionInCampus($currentPermission, $event);

            if ($inCampus) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the specified user is an EOTC Subscribed User
     * This encompasses Attendance Officer, Operations Officer, etc
     * and whatever other roles STK uses to manage their stuff
     *
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function isSubscribedUser(User $user, Event $event)
    {
        $allSubscriptions = $this->getPermissionMapper()->findSubscriptionsByUser($user);

        foreach ($allSubscriptions as $currentSubscription) {
            $inCampus  = $this->isPermissionInCampus($currentSubscription, $event);
            $hasStatus = $this->isPermissionInStatus($currentSubscription, $event);

            if ($inCampus && $hasStatus) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subscribed User has subscribed to the Event's campus
     *
     * @param EventPermission $permission
     * @param Event $event
     * @return bool
     */
    private function isPermissionInCampus(EventPermission $permission, Event $event)
    {
        foreach ($event->getCampusCollection() as $currentCampus) {
            if ($permission->getCampus()->getId() == $currentCampus->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subscribed User has subscribed to the Event's current status
     *
     * @param EventPermission $permission
     * @param Event $event
     * @return bool
     */
    private function isPermissionInStatus(EventPermission $permission, Event $event)
    {
        return $permission->getStatus()->getId() == $event->getMetadata()->getStatus()->getId();
    }

    /**
     * Allow event authors to modify status of events from In Principle
     * to the next status
     *
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function canSelfPromote(User $user, Event $event)
    {
        if ($event->getMetadata()->getStatus()->isInPrinciple()) {
            if ($event->getAuthor()->getId() == $user->getId()) {
                return true;
            }

            /* Teacher in Charge can self promote, too
             */
            if ($event->getTeacherInCharge()->getId() == $user->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether an EOTC event is in its final stages
     *
     * @param User $user
     * @param Event $event
     * @return bool
     */
    public function canFinalise(User $user, Event $event)
    {
        if ($this->isEOTCAdministrator($user)) {
            if ($event->getMetadata()->getStatus()->isApproved() || $event->getMetadata()->getStatus()->isRejected()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets whether the requested status change is allowed.
     *
     * Currently, the only barred change is that events may not be approved if
     * there are no students in the event.
     *
     * @param Event $event
     * @param EventStatus $newStatus
     *
     * @return bool
     */
    public function isStatusChangeAllowed(Event $event, EventStatus $newStatus)
    {
        $oldStatusId = $event->getMetadata()->getStatus()->getId();
        $newStatusId = $newStatus->getId();

        /* If the status changed and it's currently being approved,
         * check for student attendances
         */
        if (($oldStatusId != $newStatusId) && ($newStatusId == EventStatusMapper::STATUS_APPROVED)) {
            try {
                $studentsMandatory = (int)$this->getConfigMapper()->findOneByColumn(
                    'name',
                    'student_attendees_required'
                )->getValue() === 1;
                $noStudents        = count($event->getMetadata()->getStudentAttendees()) === 0;

                /* If students are mandatory but there are none assigned to the event,
                 * the status cannot be changed to Approved
                 */
                if ($studentsMandatory && $noStudents) {
                    return false;
                }
            } catch (MapperException $e) {

                /* noop
                 */
            }
        }

        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set permission mapper
     *
     * @param EventPermissionMapper $permissionMapper
     * @return self $this
     */
    private function setPermissionMapper($permissionMapper)
    {
        $this->permissionMapper = $permissionMapper;
        return $this;
    }

    /**
     * Get permission mapper
     *
     * @return EventPermissionMapper
     */
    private function getPermissionMapper()
    {
        return $this->permissionMapper;
    }

    /**
     * Get all permissions cache
     *
     * @return array
     */
    private function getAllPermissions()
    {
        return $this->allPermissions;
    }

    /**
     * @return ConfigMapper
     */
    private function getConfigMapper()
    {
        return $this->configMapper;
    }

    /**
     * @param ConfigMapper $configMapper
     * @return self $this
     */
    private function setConfigMapper($configMapper)
    {
        $this->configMapper = $configMapper;
        return $this;
    }
}
