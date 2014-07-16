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
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Monolog\Logger;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;

class EventMetadataNotifier extends AbstractModuleNotifier {

	const TRIGGER_SUBMITTED      = 'submitted';
	const TRIGGER_UPDATED        = 'updated';
	const TRIGGER_STATUSCHANGED  = 'statuschanged';

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
	protected function getSlug() {
		return $this->msgNamespaceSlug;
	}

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		// subconstructor: load any mapper dependencies etc. here
	}

	/**
	 * Bundle triggers
	 *
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		if($bundle->getMethod() == Mapper::METHOD_CREATE) {
			$bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
		}
		elseif($bundle->getMethod() == Mapper::METHOD_UPDATE) {

			// what KIND of update has occurred?
			$diff = $bundle->getEvent()->getDiff();

			// no diff - nothing to handle
			if(empty($diff)) {
				return;
			}

			// status has been updated:
			if(isset($diff['status'])) {
				$bundle->attach(self::TRIGGER_STATUSCHANGED, self::IMMEDIATELY);
				return;
			}

			// process the other kinds of updates
			$bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
		}
	}

	/**
	 * @param string $label
	 * @return callable[]
	 */
	public function retrieveCallbacks($label) {
		// this system bypasses the `msg_source` table lookup, since everything is sent to specific users
		$method = 'notify'.ucfirst($label);
		if(method_exists($this, $method)) {
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
	 * @param User $applicant
	 * @return Envelope
	 */
	public function notifySubmitted($eventMetadata, $applicant) {
		$envelope = new Envelope();
		$bob = new Builder();

		// retrieve the event that this metadata object is connected to
		$eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

		// no event? no messages
		if(!$eventCollection->count()) {
			return $envelope;
		}

		/** @var Event $relEvent */
		$relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());

		// inform approved in principle moderator(s)
		/** @var Collection|int[] $moderatorCollection */
		$moderatorCollection = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
			'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
			'status_id' => $eventMetadata->getStatus() instanceof EventStatus ? $eventMetadata->getStatus()->getId() : $eventMetadata->getStatus()
		))->getColumnValues('userId'));

		$bob->setTitle(":applicant submitted new EOTC, :event")
			->setParams(array(
				'applicant' => new UserParam($applicant, ParameterInterface::STACKING_REPLACE),
				'event'     => new Param($relEvent->getTitle())
			))
			->setIcon("/portrait.php?size=square32&userId=".$applicant->getExternalId())
			->setHref("/modules/eotc/status/modify/".$relEvent->getId())
			->setSeverity(Logger::WARNING)
			->setNamespace($this->getSlug())
			->addTopic("eotc.{$relEvent->getId()}.submitted");

		$instance = $bob->build();

		foreach($moderatorCollection as $mod) {
			$envelope->targetToUser($mod, $instance);
		}

		return $envelope;
	}


	/**
	 * This method will create notifications for:
	 *  - if change made by applicant or TiC: MODERATORS of the correct campus and status
	 *  - if change made by moderator: APPLICANT and TIC
	 *
	 * @param EventMetadata $eventMetadata
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyUpdated($eventMetadata, $effector, $diff = null) {
		$envelope = new Envelope();
		$bob = new Builder();

		// retrieve the event that this metadata object is connected to
		$eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

		// no event? no messages
		if(!$eventCollection->count()) {
			return $envelope;
		}

		/** @var Event $relEvent */
		$relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());
		$relEventId = $relEvent->getId();
		$relEventNote = $relEvent->getTitle();

		// pray that these are actually User objects
		$teacherInCharge = $relEvent->getTeacherInCharge();
		$teacherInChargeId = $teacherInCharge->getId();

		$applicant = $relEvent->getAuthor();
		$applicantId = $applicant->getId();

		$effectorId = $effector->getId();
		$effectorExtId = $effector->getExternalId();

		// get list of moderators relevant to the current status
		$currentStatus = $eventMetadata->getStatus();

		$moderatorIds = $this->getModerators($relEvent, $currentStatus instanceof EventStatus ? $currentStatus->getId() : (int)$currentStatus);

		// prep msg data
		$bob->setIcon("/portrait.php?size=square32&userId=$effectorExtId");
		$bob->setHref("/modules/eotc/status/modify/$relEventId");

		// change was made by applicant or TiC: inform moderators
		if($effectorId == $teacherInChargeId || $effectorId == $applicantId) {
			$inform = $moderatorIds;
		}
		// change was made by moderator (right?): inform applicant and TiC
		else {
			$inform = array($teacherInChargeId, $applicantId);
			$bob->setHref("/modules/eotc/manage/modify.php?eventID=$relEventId");
		}

		$bob->setParams(array(
				'user' => new UserParam($effector),
				'note' => new Param($relEventNote)
			))
			->setSeverity(Logger::WARNING)
			->setNamespace($this->getSlug())
			->addTopic("eotc.$relEventId.updated")
			->setMeta($diff);

		if(isset($diff['comment'])) {
			$bob->setTitle(":user commented on the EOTC event :note")
				->setByline($effector->formatName('{firstname}')." wrote")
				->setBody($diff['comment'][0]);
		}
		else {
			$bob->setTitle(":user updated the EOTC event :note");
		}

		$instance = $bob->build();

		// iterate those who should be informed and bind a message for them
		foreach($inform as $id) {
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
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStatuschanged($eventMetadata, $effector, $diff = null) {
		$envelope = new Envelope();
		list($newStatus, $oldStatus) = $diff['status'];

		$eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

		// no event? no messages
		if(!$eventCollection->count()) {
			return $envelope;
		}

		/** @var Event $relEvent */
		$relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());
		$relEventId = $relEvent->getId();
		$relEventNote = $relEvent->getTitle();

		// pray that these are actually User objects
		$teacherInCharge = $relEvent->getTeacherInCharge();
		$teacherInChargeId = $teacherInCharge->getId();

		$applicant = $relEvent->getAuthor();
		$applicantId = $applicant->getId();

		$effectorExtId = $effector->getExternalId();


		// setup initial condition of the builder
		$bob = new Builder();
		$bob->setIcon("/portrait.php?size=square32&userId=$effectorExtId")
			->setNamespace($this->getSlug())
			->addTopic("eotc.$relEventId.statuschanged")
			->setSeverity(Logger::WARNING)
			->setParams(array(
				'moderator' => new UserParam($effector),
				'event'     => new Param($relEventNote)
			));

		$hrefMod = "/modules/eotc/status/modify/$relEventId";
		$hrefOwn = "/modules/eotc/manage/modify.php?eventID=$relEventId";
		$hrefReg = "/calendar/full/".$relEvent->getStartDateTime()->getUsingFormat('d-m-Y');

		/** @var EventMetadataMapper $mapper */
		$mapper = $this->mapper;

		$ownerIds = array($teacherInChargeId, $applicantId);

		// what we do is entirely contingent on what the new status is
		switch($newStatus) {

			case EventStatusMapper::STATUS_INPRINCIPLE:
				$bob->setTitle(":moderator has approved :event in principle")
					->setHref($hrefOwn);

				// tell applicant and TiC
				foreach($ownerIds as $userId) {
					$envelope->targetToUser($userId, $bob->build());
				}
				break;

			case EventStatusMapper::STATUS_DETAILSCOMPLETED:
				$bob->setTitle(":moderator has approved RAMs for :event")
					->setHref($hrefMod);
				break;

			case EventStatusMapper::STATUS_RAMSCONFIRMED:
				$bob->setTitle(":moderator has confirmed RAMs for :event")
				    ->setHref($hrefMod);
				break;

			case EventStatusMapper::STATUS_APPROVED:
				$bob->setTitle(":moderator has given final approval for :event")
					->setHref($hrefOwn);

				// tell applicant, TiC...
				foreach($ownerIds as $userId) {
					$envelope->targetToUser($userId, $bob->build());
				}

				// ...relevant staff...
				$bob->setHref($hrefReg);
				foreach($mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id') as $userId) {
					$envelope->targetToUser($userId, $bob->build());
				}

				// ...Attendance Officer and Operations Officer
				// TODO who are the AO and OO? Don't know where they're defined...
				break;

			case EventStatusMapper::STATUS_REJECTED:
				$bob->setTitle(":moderator has cancelled :event");

				// in this case, tell. freaking. everybody.
				if($oldStatus == EventStatusMapper::STATUS_APPROVED) {
					// tell relevant staff
					$bob->setHref($hrefReg);
					foreach($mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id') as $userId) {
						$envelope->targetToUser($userId, $bob->build());
					}

					// include Attendance Officer and Operations Officer
					// TODO who are the AO and OO? Don't know where they're defined...

					// if Resources booked, inform Resource Moderator(s)
					// TODO who are the AO and OO? Don't know where they're defined...

					// if RAMS, inform RAMS Approval Moderator(s)
					if($eventMetadata->getRamsRequired()) {
						$bob->setHref($hrefMod);

						$ramsModIds = $this->getModerators($relEvent, EventStatusMapper::STATUS_DETAILSCOMPLETED);

						foreach($ramsModIds as $userId) {
							$envelope->targetToUser($userId, $bob->build());
						}
					}
				}

				// in any case, the teacher in charge and applicant will be informed
				$bob->setHref($hrefOwn);
				foreach($ownerIds as $id) {
					$envelope->targetToUser($id, $bob->build());
				}
				break;

		}

		// get list of moderators relevant to the current status
		$currentStatus = $eventMetadata->getStatus();
		$moderatordIds = $this->getModerators($relEvent, $currentStatus instanceof EventStatus ? $currentStatus->getId() : (int)$currentStatus);

		// inform them
		$bob->setHref($hrefMod);
		foreach($moderatordIds as $modId) {
			$envelope->targetToUser($modId, $bob->build());
		}

		return $envelope;
	}


	/**
	 * Returns an array of user IDs that have mod privilege for the given event/status combination
	 *
	 * @param Event     $event        the event in question
	 * @param int|array $targetStatus can be an array of statuses
	 * @return array
	 */
	protected function getModerators($event, $targetStatus) {
		$campusIds = array();
		// coerce "campuses" out of their "collection"
		// because PHP lacks any form of reassurance that what something is meant to contain actually DOES contain it
		foreach($event->getCampusCollection()->getAll() as $unknown) {
			$campusIds[] = $unknown instanceof Campus ? $unknown->getId() : (int)$unknown;
		}

		// the field is called userId, yet it MIGHT contain userIds OR User objects...
		$userUnknowns = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
			'campus_id' => $campusIds,
			'status_id' => $targetStatus
		))->getColumnValues('userId'));

		$userIds = array();

		foreach($userUnknowns as $unknown) {
			$userIds[] = $unknown instanceof User ? $unknown->getId() : (int)$unknown;
		}

		return array_unique($userIds);
	}

}
