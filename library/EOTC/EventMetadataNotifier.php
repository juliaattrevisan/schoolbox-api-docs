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


use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
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
	 * An outdated interface method?
	 *
	 * @param string $poop
	 * @param string $label
	 * @return string|void
	 */
	public function buildTopic($poop, $label) {
	}

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		// subconstructor: load any mapper dependencies etc. here
		$this->namespace = NSMapper::getInstance()->findOneBySlug($this->getSlug());
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
	 * @param EventMetadata $eventMetadata
	 * @param User $applicant
	 * @return Messenger
	 */
	public function notifySubmitted($eventMetadata, $applicant) {
		$msgr = new Messenger();

		// retrieve the event that this metadata object is connected to
		$eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

		// no event? no messages
		if(!$eventCollection->count()) {
			return $msgr;
		}

		/** @var Event $relEvent */
		$relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());

		// inform approved in principle moderator(s)
		/** @var Collection|int[] $moderatorCollection */
		$moderatorCollection = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
			'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
			'status_id' => $eventMetadata->getStatus()
		))->getColumnValues('userId'));

		$icon = "/portrait.php?size=square32&userId=".$applicant->getExternalId();
		$href = "/modules/eotc/status/modify/".$relEvent->getId();

		$msg = new Message(":applicant submitted new EOTC, :event", $icon, $href);

		foreach($moderatorCollection as $mod) {
			$msgr->targetToUser($mod, $msg, "eotc.{$relEvent->getId()}.submitted", Logger::WARNING, array(
				'applicant' => array($applicant->formatName(), "/search/user/".$applicant->getId()),
				'event'     => $relEvent->getNote()
			));
		}

		return $msgr;
	}

	/**
	 * @param EventMetadata $eventMetadata
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyUpdated($eventMetadata, $effector, $diff = null) {
		$msgr = new Messenger();

		// retrieve the event that this metadata object is connected to
		$eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

		// no event? no messages
		if(!$eventCollection->count()) {
			return $msgr;
		}

		/** @var Event $relEvent */
		$relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());
		$relEventId = $relEvent->getId();
		$relEventNote = $relEvent->getNote();

		// pray that these are actually User objects
		$teacherInCharge = $relEvent->getTeacherInCharge();
		$teacherInChargeId = $teacherInCharge->getId();

		$applicant = $relEvent->getAuthor();
		$applicantId = $applicant->getId();

		$effectorId = $effector->getId();
		$effectorName = $effector->formatName();
		$effectorExtId = $effector->getExternalId();

		// get list of moderators relevant to the current status
		// TODO this does not presently cover resource booking moderators or final approvers (they don't exist in the table)
		$moderatorCollection = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
			'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
			'status_id' => $eventMetadata->getStatus()
		))->getColumnValues('userId'));

		$moderatorIds = $moderatorCollection->getColumnValues('id');

		// prep msg data
		$icon = "/portrait.php?size=square32&userId=$effectorExtId";
		$href = "/modules/eotc/status/modify/$relEventId";

		// change was made by applicant or TiC: inform moderators
		if($effectorId == $teacherInChargeId || $effectorId == $applicantId) {
			$inform = $moderatorIds;
		}
		// change was made by moderator: inform applicant and TiC
		elseif(in_array($effectorId, array($teacherInChargeId, $applicantId))) {
			$inform = array($teacherInChargeId, $applicantId);
			$href = "/modules/eotc/manage/modify.php?eventID=$relEventId";
		}
		else {
			$inform = array();
		}

		// TODO should this message be less generic by reading the diff?
		$msg = new Message(":user updated the EOTC event :note", $icon, $href);

		// iterate those who should be informed and bind a message for them
		foreach($inform as $id) {
			$msgr->targetToUser($id, $msg, "eotc.$relEventId.updated", Logger::WARNING, array(
				'user' => array($effectorName, "/search/user/$effectorId"),
				'note' => $relEventNote
			));
		}

		return $msgr;
	}

	/**
	 * @param EventMetadata $eventMetadata
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStatuschanged($eventMetadata, $effector, $diff = null) {
		$msgr = new Messenger();
		list($newStatus, $oldStatus) = $diff['status'];

		$eventCollection = EventMetadataMapper::getInstance()->findEventsForMetadata($eventMetadata);

		// no event? no messages
		if(!$eventCollection->count()) {
			return $msgr;
		}

		/** @var Event $relEvent */
		$relEvent = EventMapper::getInstance()->findById($eventCollection->first()->getId());
		$relEventId = $relEvent->getId();
		$relEventNote = $relEvent->getNote();

		// pray that these are actually User objects
		$teacherInCharge = $relEvent->getTeacherInCharge();
		$teacherInChargeId = $teacherInCharge->getId();

		$applicant = $relEvent->getAuthor();
		$applicantId = $applicant->getId();

		$effectorId = $effector->getId();
		$effectorName = $effector->formatName();
		$effectorExtId = $effector->getExternalId();

		$informMod = array();
		$informOwn = array();
		$informReg = array();

		$icon = "/portrait.php?size=square32&userId=$effectorExtId";
		$hrefMod = "/modules/eotc/status/modify/$relEventId";
		$hrefOwn = "/modules/eotc/manage/modify.php?eventID=$relEventId";
		$hrefReg = "/calendar/full/".$relEvent->getStartDateTime()->getUsingFormat('d-m-Y');

		$params = array(
			'moderator' => array($effectorName, "/search/user/$effectorId"),
			'event'     => $relEventNote
		);

		switch($newStatus) {

			case EventStatusMapper::STATUS_INPRINCIPLE:
				$text = ":moderator has approved :event in principle";
				// tell applicant and TiC
				$informOwn = array($teacherInChargeId, $applicantId);
				break;

			case EventStatusMapper::STATUS_RAMSAPPROVED:
				$text = ":moderator has approved RAMs for :event";
				// tell Final Approval moderator(s)
				$moderatorCollection = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
					'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
					'status_id' => EventStatusMapper::STATUS_APPROVED
				))->getColumnValues('userId'));

				$informMod = $moderatorCollection->getAll();
				break;

			case EventStatusMapper::STATUS_APPROVED:
				$text = ":moderator has given final approval for :event";
				// tell applicant, TiC, relevant staff, Attendance Officer and Operations Officer
				$informReg = $this->mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id');
				$informOwn = array($teacherInChargeId, $applicantId);

				// TODO who are the AO and OO? Don't know where they're defined...
				break;

			case EventStatusMapper::STATUS_REJECTED:
				$text = ":moderator has cancelled :event";
				$inform = array();

				// in this case, tell. freaking. everybody. run. fun fast.
				if($oldStatus == EventStatusMapper::STATUS_APPROVED) {
					// include relevant staff
					$informReg = $this->mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id');

					// include Attendance Officer and Operations Officer
					// TODO who are the AO and OO? Don't know where they're defined...

					// if Resources booked, inform Resource Moderator(s)
					// TODO who are the AO and OO? Don't know where they're defined...

					// if RAMS, inform RAMS Approval Moderator(s)
					if($eventMetadata->getRamsRequired()) {
						/** @var Collection|User[] $moderatorCollection */
						$informMod = EventPermissionMapper::getInstance()->findByColumnList(array(
							'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
							'status_id' => EventStatusMapper::STATUS_RAMSAPPROVED
						))->getColumnValues('userId');

						foreach($moderatorCollection as $user) {
							$inform[] = $user;
						}
					}
				}
				// in any case, the teacher in charge and applicant will be informed
				$informOwn = array($teacherInChargeId, $applicantId);
				break;

			default:
				// not an acceptable status, so send no messages
				return $msgr;
		}

		// bind the messages to their various contexts
		if(count($informMod)) {
			$msgMod = new Message($text, $icon, $hrefMod);
			foreach($informMod as $userId) {
				$msgr->targetToUser($userId, $msgMod, "eotc.$relEventId.statuschanged",Logger::WARNING, $params);
			}
		}
		if(count($informOwn)) {
			$msgOwn = new Message($text, $icon, $hrefOwn);
			foreach($informOwn as $userId) {
				$msgr->targetToUser($userId, $msgOwn, "eotc.$relEventId.statuschanged",Logger::WARNING, $params);
			}
		}
		if(count($informReg)) {
			$msgReg = new Message($text, $icon, $hrefReg);
			foreach($informReg as $userId) {
				$msgr->targetToUser($userId, $msgReg, "eotc.$relEventId.statuschanged",Logger::WARNING, $params);
			}
		}

		return $msgr;
	}

}
