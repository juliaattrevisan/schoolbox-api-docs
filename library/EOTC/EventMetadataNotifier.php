<?php
/**
 * EventMetadataNotifier
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;

class EventMetadataNotifier extends AbstractModuleNotifier
{

    const TRIGGER_SUBMITTED = 'submitted';
    const TRIGGER_UPDATED = 'updated';
    const TRIGGER_STATUSCHANGED = 'statuschanged';

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
     * Bundle triggers
     *
     * @param TriggerBundle $bundle
     *
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
        } elseif ($bundle->getMethod() == Mapper::METHOD_UPDATE) {

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
                'status_id' => $eventMetadata->getStatus() instanceof EventStatus ?
                    $eventMetadata->getStatus()->getId() :
                    $eventMetadata->getStatus()
            )
        );

        $href = "/modules/eotc/status/modify/".$relEvent->getId();
        $bob->setTitle(":applicant submitted new EOTC, :event")
            ->setParams(
                array(
                    'applicant' => new UserParam($applicant, ParameterInterface::STACKING_REPLACE),
                    'event'     => new Param($relEvent->getTitle(), $href)
                )
            )
            ->setIcon("/portrait.php?size={$this->iconSize}&userId=".$applicant->getExternalId())
            ->setHref($href)
            ->setSeverity(Logger::WARNING)
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.{$relEvent->getId()}.submitted");

        $instance = $bob->build();

        foreach ($permissionCollection as $permission) {
            if ($permission->getSubscribed() || $permission->getAllowed()) {
                $envelope->targetToUser($permission->getUserId(), $instance);
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
        $effectorExtId     = $effector->getExternalId();

        // get list of moderators relevant to the current status
        $currentStatus = $eventMetadata->getStatus();
        $moderatorIds  = $this->getInterestedUsers(
            $relEvent,
            $currentStatus instanceof EventStatus ? $currentStatus->getId() : (int)$currentStatus
        );

        // prep msg data
        $bob->setIcon("/portrait.php?size={$this->iconSize}&userId=$effectorExtId");
        $href = "/modules/eotc/status/modify/$relEventId";

        // change was made by applicant or TiC: inform moderators
        if ($effectorId == $teacherInChargeId || $effectorId == $applicantId) {
            $inform = $moderatorIds;
        } // change was made by moderator (right?): inform applicant and TiC
        else {
            $inform = array($teacherInChargeId, $applicantId);
            $href = "/modules/eotc/manage/modify.php?eventID=$relEventId";
        }

        $bob->setHref($href)
            ->setParams(
                array(
                    'user' => new UserParam($effector),
                    'note' => new Param($relEventNote, $href)
                )
            )
            ->setSeverity(Logger::WARNING)
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.$relEventId.updated")
            ->setMeta($diff);

        if (isset($diff['comment'])) {
            $bob->setTitle(":user commented on the EOTC event :note")
                ->setByline($effector->formatName('{firstname}') . " wrote")
                ->setBody($diff['comment'][0]);
        } else {
            $bob->setTitle(":user updated the EOTC event :note");
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
        $effectorExtId     = $effector->getExternalId();


        // setup initial condition of the builder
        $bob = new Builder();
        $bob->setIcon("/portrait.php?size={$this->iconSize}&userId=$effectorExtId")
            ->setNamespace($this->getSlug())
            ->addTopic("eotc.$relEventId.statuschanged")
            ->setSeverity(Logger::WARNING)
            ->setParam('moderator', new UserParam($effector));

        $hrefMod = "/modules/eotc/status/modify/$relEventId";
        $hrefOwn = "/modules/eotc/manage/modify.php?eventID=$relEventId";
        $hrefReg = "/calendar/full/" . $relEvent->getStartDateTime()->getURLDate();

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

                        $ramsModIds = $this->getInterestedUsers($relEvent, EventStatusMapper::STATUS_DETAILSCOMPLETED);

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
     * @param Event     $event        the event in question
     * @param int|array $targetStatus can be an array of statuses
     *
     * @return array
     */
    protected function getInterestedUsers($event, $targetStatus)
    {
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
            // the field is called userId, yet it MIGHT contain userIds OR User objects...
            if ($permission->getSubscribed() || $permission->getAllowed()) {
                $unknown   = $permission->getUserId();
                $userIds[] = $unknown instanceof User ? $unknown->getId() : (int)$unknown;
            }
        }
        return array_unique($userIds);
    }

}
