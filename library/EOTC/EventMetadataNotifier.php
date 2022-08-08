<?php

/**
 * EventMetadataNotifier
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Mapper;
use Schoolbox\Realtime;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;
use Alaress_Date_Format;

class EventMetadataNotifier extends AbstractModuleNotifier
{
    const TRIGGER_SUBMITTED     = 'submitted';
    const TRIGGER_UPDATED       = 'updated';
    const TRIGGER_STATUSCHANGED = 'statuschanged';
    const TRIGGER_UPCOMING      = 'upcoming';

    /**
     * Namespace slug, as defined in msg_namespace
     *
     * @var string
     */
    private $msgNamespaceSlug = 'eotc';

    /**
     * Get le slug
     *
     * @return string
     */
    protected function getSlug()
    {
        return $this->msgNamespaceSlug;
    }

    /**
     * A l10n dictionary
     *
     * @var RosettaStone
     */
    private $stone;

    /**
     * This depends on the Rosetta Stone
     *
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        $stoneFactory = new RosettaStoneFactory();
        $this->stone  = $stoneFactory->build(\Schoolbox\L10n\Mapper::getInstance());
        parent::loadDependencies($dependencies);
    }

    /**
     * Bundle triggers
     *
     * @param TriggerBundle $bundle
     *
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        $detachUpcoming = false;
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
        } elseif ($bundle->getMethod() == Mapper::METHOD_UPDATE) {

            /** @var EventMetadata $object */
            $object = $bundle->getEvent()->getObject();
            $status = $object->getStatus();

            // if in a final state, rescind upcoming reminders
            if ($status->isApproved() || $status->isRejected()) {
                $detachUpcoming = true;
                $bundle->detach(self::TRIGGER_UPCOMING);
            }

            // what KIND of update has occurred?
            $diff = $bundle->getEvent()->getDiff();

            // no diff - nothing to handle
            if (empty($diff)) {
                return;
            }

            // status has been updated:
            if (isset($diff['status'])) {
                $bundle->attach(self::TRIGGER_STATUSCHANGED, self::IMMEDIATELY);
                return;
            }

            // process the other kinds of updates
            $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
        }

        if (!$detachUpcoming) {
            $bundle->attach(self::TRIGGER_UPCOMING, array($this, 'getNextReminderTime'));
        }
    }


    /**
     * This is a callback method for deriving the time for a recurring reminder trigger
     *
     * @param Realtime\Schedule\Event\Event $event
     * @return int
     */
    public function getNextReminderTime(Realtime\Schedule\Event\Event $event)
    {
        /** @var EventMetadata $object */
        $object = $event->getObject();

        // retrieve the event that this metadata object is connected to
        $eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($object);

        if (!$eventCollection->count()) {
            // this shouldn't happen, but we have no recourse for it
            return -1;
        }

        $now = \Alaress_Date_Format::now();

        $startTime = EventMapper::getInstance()->findById($eventCollection->first()->getId())->getStartDateTime();

        $eotcConf  = ConfigMapper::getInstance();

        // rewind first reminder `n` days from start time
        $firstReminder = clone $startTime;
        $firstReminder->addDays(-1 * $eotcConf->findFirstReminderThreshold()->getValue());

        if ($firstReminder->gte($now)) {
            // if this first reminder is in the future, schedule it
            return $firstReminder->getUnixTimeStamp();
        }

        // rewind daily reminder `m` days from start time
        $dailyReminder = clone $startTime;
        $dailyReminder->addDays(-1 * $eotcConf->findDailyReminderThreshold()->getValue());

        while ($dailyReminder->lt($now)) {
            // increment by days until it's in the past
            $dailyReminder->addDays(1);
        }

        if ($dailyReminder->lt($startTime)) {
            // so long as the reminder is before the actual start date, return that
            return $dailyReminder->getUnixTimeStamp();
        }

        // all reminders are in the past. discard this trigger
        return -1;
    }


    /**
     * @param string $label
     *
     * @return callable[]
     */
    public function retrieveCallbacks($label)
    {
        // this system bypasses the `msg_source` table lookup, since everything is sent to specific users
        $method = 'notify' . ucfirst($label);
        if (method_exists($this, $method)) {
            return array(
                array($this, $method)
            );
        }
        return array();
    }


    /**
     * @param EventMetadata $eventMetadata
     * @param User          $effector
     * @param array         $diff
     * @return Envelope
     */
    public function notifyUpcoming($eventMetadata, $effector, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // retrieve the event that this metadata object is connected to
        $eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);


        // no event? no messages
        if (!$eventCollection->count()) {
            // Don't translate log messages
            $this->log->error("No events attached to EOTC metadata");
            return $envelope;
        }

        /** @var Event $relEvent */
        $relEvent      = EventMapper::getInstance()->findById($eventCollection->first()->getId());
        $relEventId    = $relEvent->getId();
        $relEventStart = $relEvent->getStartDateTime();
        $currentStatus = $relEvent->getMetadata()->getStatus();

        // Don't notify if event is in a final state.
        // (Required because some notifs may be awaiting, and not rescinded)
        // TODO is this able to be an int?
        if ($currentStatus->isApproved() || $currentStatus->isRejected()) {
            $this->log->notice("Event is in final state - no upcoming notification will be sent");
            return $envelope;
        }

        $userIds = $this->getInterestedUsers($relEvent, $currentStatus);
        array_push($userIds, $relEvent->getAuthor()->getId());

        // Envelope can't possibly be empty if we just pushed something onto it.
//        if (count($userIds) == 0) {
//            // no audience? no messages
//            $this->log->error("No audience for EOTC $relEventId");
//            return $envelope;
//        }

        // compensate for timing issues by pushing "now" back a few minutes.
        $days = $relEventStart->diffInDays((new \Alaress_Date_Format(time(), Alaress_Date_Format::TIMESTAMP))->addMinutes(-10));

        if ($days == 0) {
            // wut.
            $days = 1;
        }

        $href = "/modules/eotc/status/modify/$relEventId";

        $bob->setTitle(sprintf("Warning: :event starts %s, but has not been given final approval", $days == 1 ? 'tomorrow' : "in $days days"))
            ->setHref($href)
            ->setParams(array(
                'event' => new Param($relEvent->getTitle(), $href)
            ))
            ->setSeverity(Logger::WARNING)
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.$relEventId." . self::TRIGGER_UPCOMING)
            ->setIcon("/images/icon.php?size=" . $this->iconSize . "&icon=" . Realtime\Message\NSMapper::NS_EOTC)
            ->setAllowSelfSend(true);

        $msg = $bob->build();

        foreach ($userIds as $userId) {
            $envelope->targetToUser($userId, $msg);
        }

        return $envelope;
    }


    /**
     * This method will create notifications for:
     *  - MODERATORS of the correct campus and status
     *
     * @param EventMetadata $eventMetadata
     * @param User          $applicant
     *
     * @return Envelope
     */
    public function notifySubmitted($eventMetadata, $applicant)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // retrieve the event that this metadata object is connected to
        $eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

        // no event? no messages
        if (!$eventCollection->count()) {
            return $envelope;
        }

        /** @var Event $relEvent */
        $relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());

        // inform approved in principle moderator(s)
        /** @var Collection|EventPermission[] $permissionCollection */
        $permissionCollection = EventPermissionMapper::getInstance()->findByColumnList(
            array(
                'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
                'status_id' => $eventMetadata->getStatus()->getId()
            )
        );

        $href = "/modules/eotc/status/modify/" . $relEvent->getId();
        $bob->setTitle(":applicant submitted new :eotc, :event")
            ->setParams(
                array(
                    'applicant' => new UserParam($applicant, ParameterInterface::STACKING_REPLACE),
                    'event'     => new Param($relEvent->getTitle(), $href),
                    'eotc'      => new Param($this->stone->translate('eotc', false, false)),
                )
            )
            ->setIcon($this->getUserIcon($applicant))
            ->setHref($href)
            ->setSeverity(Logger::WARNING)
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.{$relEvent->getId()}.submitted");

        $instance = $bob->build();

        foreach ($permissionCollection as $permission) {
            if ($permission->getSubscribed() || $permission->getAllowed()) {
                $envelope->targetToUser($permission->getUser()->getId(), $instance);
            }
        }

        return $envelope;
    }


    /**
     * This method will create notifications for:
     *  - if change made by applicant or TiC: MODERATORS of the correct campus and status
     *  - if change made by moderator: APPLICANT and TIC
     *
     * @param EventMetadata $eventMetadata
     * @param User          $effector
     * @param array         $diff
     *
     * @return Envelope
     */
    public function notifyUpdated($eventMetadata, $effector, $diff = null)
    {
        $envelope = new Envelope();
        $bob      = new Builder();

        // retrieve the event that this metadata object is connected to
        $eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

        // no event? no messages
        if (!$eventCollection->count()) {
            return $envelope;
        }

        /** @var Event $relEvent */
        $relEvent     = EventMapper::getInstance()->findById($eventCollection->first()->getId());
        $relEventId   = $relEvent->getId();
        $relEventNote = $relEvent->getTitle();

        // pray that these are actually User objects
        $teacherInCharge   = $relEvent->getTeacherInCharge();
        $teacherInChargeId = $teacherInCharge->getId();
        $applicant         = $relEvent->getAuthor();
        $applicantId       = $applicant->getId();
        $effectorId        = $effector->getId();

        // get list of moderators relevant to the current status
        $currentStatus = $eventMetadata->getStatus();
        $moderatorIds  = $this->getInterestedUsers($relEvent, $currentStatus);

        // prep msg data
        $bob->setIcon($this->getUserIcon($effector));
        $href = "/modules/eotc/status/modify/$relEventId";

        if ($effectorId == $teacherInChargeId || $effectorId == $applicantId) {
            // change was made by applicant or TiC: inform moderators
            $inform = $moderatorIds;
        } else {
            // change was made by moderator (right?): inform applicant and TiC
            $inform = array($teacherInChargeId, $applicantId);
            $href = "/modules/eotc/manage/modify?eventID=$relEventId";
        }

        $bob->setHref($href)
            ->setParams(
                array(
                    'user' => new UserParam($effector),
                    'note' => new Param($relEventNote, $href),
                    'eotc' => new Param($this->stone->translate('eotc', false, false)),
                )
            )
            ->setSeverity(Logger::WARNING)
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.$relEventId.updated")
            ->setMeta($diff);

        if (isset($diff['comment'])) {
            $bob->setTitle(":user commented on the :eotc event :note")
                ->setByline($effector->formatName('{givenname}') . " wrote")
                ->setBody($diff['comment'][0]);
        } else {
            $bob->setTitle(":user updated the :eotc event :note");
        }

        $instance = $bob->build();

        // iterate those who should be informed and bind a message for them
        foreach ($inform as $id) {
            $envelope->targetToUser($id, $instance);
        }

        return $envelope;
    }


    /**
     * This method will create notifications for:
     *  - if change made by applicant or TiC: MODERATORS of the correct campus and status
     *  - if change made by moderator: APPLICANT and TIC
     *
     * @param EventMetadata $eventMetadata
     * @param User          $effector
     * @param array         $diff
     *
     * @return Envelope
     */
    public function notifyStatuschanged($eventMetadata, $effector, $diff = null)
    {
        $envelope = new Envelope();
        list($newStatus, $oldStatus) = $diff['status'];

        $eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

        // no event? no messages
        if (!$eventCollection->count()) {
            return $envelope;
        }

        /** @var Event $relEvent */
        $relEvent     = EventMapper::getInstance()->findById($eventCollection->first()->getId());
        $relEventId   = $relEvent->getId();
        $relEventNote = $relEvent->getTitle();
        // pray that these are actually User objects
        $teacherInCharge   = $relEvent->getTeacherInCharge();
        $teacherInChargeId = $teacherInCharge->getId();
        $applicant         = $relEvent->getAuthor();
        $applicantId       = $applicant->getId();

        // setup initial condition of the builder
        $bob = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.$relEventId.statuschanged")
            ->setSeverity(Logger::WARNING)
            ->setParam('moderator', new UserParam($effector));

        $hrefMod = "/modules/eotc/status/modify/$relEventId";
        $hrefOwn = "/modules/eotc/manage/modify?eventID=$relEventId";
        $hrefReg = "/calendar/" . $relEvent->getStartDateTime()->getURLDate();

        /** @var EventMetadataMapper $mapper */
        $mapper   = $this->mapper;
        $ownerIds = array($teacherInChargeId, $applicantId);

        // what we do is entirely contingent on what the new status is
        switch ($newStatus) {
            case EventStatusMapper::STATUS_INPRINCIPLE:
                $bob->setTitle(":moderator has approved :event in principle")
                    ->setHref($hrefOwn)
                    ->setParam('event', new Param($relEventNote, $hrefOwn));

                // tell applicant and TiC
                foreach ($ownerIds as $userId) {
                    $envelope->targetToUser($userId, $bob->build());
                }
                break;

            case EventStatusMapper::STATUS_DETAILSCOMPLETED:
                $bob->setTitle(":moderator has completed details for :event")
                    ->setHref($hrefMod)
                    ->setParam('event', new Param($relEventNote, $hrefMod));
                break;

            case EventStatusMapper::STATUS_READYFORFINALAPPROVAL:
                $bob->setTitle(":moderator has made :event ready for final approval")
                    ->setHref($hrefMod)
                    ->setParam('event', new Param($relEventNote, $hrefMod));
                break;

            case EventStatusMapper::STATUS_APPROVED:
                $bob->setTitle(":moderator has given final approval for :event")
                    ->setHref($hrefOwn)
                    ->setParam('event', new Param($relEventNote, $hrefOwn));

                // tell applicant, TiC...
                foreach ($ownerIds as $userId) {
                    $envelope->targetToUser($userId, $bob->build());
                }

                // ...relevant staff...
                $bob->setHref($hrefReg)
                    ->setParam('event', new Param($relEventNote, $hrefReg));
                foreach ($mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id') as $userId) {
                    $envelope->targetToUser($userId, $bob->build());
                }

                break;

            case EventStatusMapper::STATUS_REJECTED:
                $bob->setTitle(":moderator has cancelled :event");

                // in this case, tell. freaking. everybody.
                if ($oldStatus == EventStatusMapper::STATUS_APPROVED) {
                    // tell relevant staff
                    $bob->setHref($hrefReg)
                        ->setParam('event', new Param($relEventNote, $hrefReg));
                    foreach ($mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id') as $userId) {
                        $envelope->targetToUser($userId, $bob->build());
                    }

                    // if RAMS, inform RAMS Approval Moderator(s)
                    if ($eventMetadata->getRamsRequired()) {
                        $bob->setHref($hrefMod)
                            ->setParam('event', new Param($relEventNote, $hrefMod));

                        $ramsModIds = $this->getInterestedUsers($relEvent, EventStatusMapper::getInstance()->findById(EventStatusMapper::STATUS_DETAILSCOMPLETED));

                        foreach ($ramsModIds as $userId) {
                            $envelope->targetToUser($userId, $bob->build());
                        }
                    }
                }

                $bob->setHref($hrefReg)
                    ->setParam('event', new Param($relEventNote, $hrefReg));

                // Tell everyone in the activity log that this event got cancelled
                foreach ($relEvent->getMetadata()->getActivityLog() as $log) {
                    /** @var \EOTC\ActivityLog\Log $log */
                    $envelope->targetToUser($log->getActioner()->getId(), $bob->build());
                }
                // in any case, the teacher in charge and applicant will be informed.
                // This includes Attendance Officer and Operations Officer.
                $bob->setHref($hrefOwn)
                    ->setParam('event', new Param($relEventNote, $hrefOwn));
                foreach ($ownerIds as $id) {
                    $envelope->targetToUser($id, $bob->build());
                }
                break;
        }

        // get list of moderators relevant to the current status
        $moderatorIds = $this->getInterestedUsers($relEvent, $newStatus);

        // inform them
        $bob->setHref($hrefMod)
            ->setParam('event', new Param($relEventNote, $hrefMod));
        foreach ($moderatorIds as $modId) {
            $envelope->targetToUser($modId, $bob->build());
        }

        return $envelope;
    }


    /**
     * Returns an array of user IDs that have mod privilege or are subscribed
     * for the given event/status combination.
     * This includes the Attendance Officer and Operations Officer - it's
     * assumed that these users will be subscribed to Approved and Cancelled
     * events.
     *
     * @param Event           $event        the event in question
     * @param EventStatus|int $targetStatus can be an array of statuses
     *
     * @return array
     */
    protected function getInterestedUsers(Event $event, $targetStatus)
    {
        if ($targetStatus instanceof EventStatus) {
            // normalise to int
            $targetStatus = $targetStatus->getId();
        }

        $campusIds = array();
        // coerce "campuses" out of their "collection"
        // because PHP lacks any form of reassurance that what something is meant to contain actually DOES contain it
        foreach ($event->getCampusCollection()->getAll() as $unknown) {
            $campusIds[] = $unknown instanceof Campus ? $unknown->getId() : (int)$unknown;
        }

        $permissions = EventPermissionMapper::getInstance()->findByColumnList(
            array(
                'campus_id' => $campusIds,
                'status_id' => $targetStatus,
            )
        );
        $userIds     = array();
        foreach ($permissions as $permission) {
            /** @var EventPermission $permission */
            if ($permission->getSubscribed() || $permission->getAllowed()) {
                $userId   = $permission->getUser()->getId();
                $userIds[] = $userId;
            }
        }
        return array_unique($userIds);
    }
}
