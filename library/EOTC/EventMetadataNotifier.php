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
		return 'eotc';
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
		$relEvent = $eventCollection->first();
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

		// TODO should this message be less generic by reading the diff?
		$msg = new Message(":user updated the EOTC event :note", $icon, $href);

		// change was made by applicant or TiC: inform moderators
		if($effectorId == $teacherInChargeId || $effectorId == $applicantId) {
			$inform = $moderatorIds;
		}
		// change was made by moderator: inform applicant and TiC
		elseif(in_array($effectorId, array($teacherInChargeId, $applicantId))) {
			$inform = array($teacherInChargeId, $applicantId);
		}
		else {
			$inform = array();
		}

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
		$relEvent = $eventCollection->first();
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


		$icon = "/portrait.php?size=square32&userId=$effectorExtId";
		$href = "/modules/eotc/status/modify/$relEventId";

		$params = array(
			'moderator' => array($effectorName, "/search/user/$effectorId"),
			'event'     => $relEventNote
		);

		switch($newStatus) {

			case EventStatusMapper::STATUS_INPRINCIPLE:
				$text = ":moderator has approved :event in principle";
				// tell applicant and TiC
				$inform = array($teacherInChargeId, $applicantId);
				break;

			case EventStatusMapper::STATUS_RAMSAPPROVED:
				$text = ":moderator has approved RAMs for :event";
				// tell Final Approval moderator(s)
				$moderatorCollection = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
					'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
					'status_id' => EventStatusMapper::STATUS_APPROVED
				))->getColumnValues('userId'));

				$inform = $moderatorCollection->getColumnValues('id');
				break;

			case EventStatusMapper::STATUS_APPROVED:
				$text = ":moderator has given final approval for :event";
				// tell applicant, TiC, relevant staff, Attendance Officer and Operations Officer
				$inform = $this->mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id');
				$inform[] = $teacherInChargeId;
				$inform[] = $applicantId;

				// TODO who are the AO and OO? Don't know where they're defined...
				break;

			case EventStatusMapper::STATUS_REJECTED:
				$text = ":moderator has cancelled :event";
				$inform = array();

				// in this case, tell. freaking. everybody. run. fun fast.
				if($oldStatus == EventStatusMapper::STATUS_APPROVED) {
					// include relevant staff
					$inform = $this->mapper->findStaffAttendees($eventMetadata->getId())->getColumnValues('id');

					// include Attendance Officer and Operations Officer
					// TODO who are the AO and OO? Don't know where they're defined...

					// if Resources booked, inform Resource Moderator(s)
					// TODO who are the AO and OO? Don't know where they're defined...

					// if RAMS, inform RAMS Approval Moderator(s)
					if($eventMetadata->getRamsRequired()) {
						/** @var Collection|User[] $moderatorCollection */
						$moderatorCollection = new Collection(EventPermissionMapper::getInstance()->findByColumnList(array(
							'campus_id' => $relEvent->getCampusCollection()->getColumnValues('id'),
							'status_id' => EventStatusMapper::STATUS_RAMSAPPROVED
						))->getColumnValues('userId'));

						foreach($moderatorCollection as $user) {
							$inform[] = $user->getId();
						}
					}

				}

				// in any case, the teacher in charge and applicant will be informed
				$inform[] = $teacherInChargeId;
				$inform[] = $applicantId;

				break;

			default:
				// not an acceptable status, so send no messages
				return $msgr;
		}

		$msg = new Message($text, $icon, $href);

		foreach($inform as $userId) {
			$msgr->targetToUser($userId, $msg, "eotc.$relEventId.statuschanged",Logger::WARNING, $params);
		}

		return $msgr;
	}





//	/**
//	 * GOTCHA! METHOD MUST MATCH: 'notify' . strtoupper(`msg_source`.`role_type`) . strtoupper(`msg_source`.`key`)
//	 *
//	 * @param \EOTC\EventMetadata $metadata
//	 * @param $creator
//	 * @param array $diff
//	 * @return Messenger
//	 */
//	public function notifyStaffChanged($metadata, $creator, $diff = null) {
//		$msgr = new Messenger();
//
//		/* Only get the first event belonging to this metadata
//		 * In series events, multiple events share the same metadata
//		 * but we only need to use the name of the event
//		 */
//		$allEvents = $this->mapper->findEventsForMetadata($metadata);
//
//		/* If the metadata can't be matched to an event, just ignore everything
//		 */
//		if ($allEvents->count()) {
//
//			/* Find a bunch of people to whom this notification should be sent
//			 * This is based on the EOTC permissions given to users
//			 */
//			$permissionMapper = new \EOTC\EventPermissionMapper();
//			$allPermissions = $permissionMapper->findByColumnList(array(
//				'status_id' => $metadata->getStatus()->getId()
//			));
//
//			/* Find all the users who can modify the EOTC event in its
//			 * current status
//			 */
//			$userIds = array();
//
//			foreach($allPermissions as $currentPermission) {
//				$userIds[] = $currentPermission->getUserId();
//			}
//
//			$userMapper = new \Schoolbox\UserMapper();
//			$allUsers = $userMapper->findByColumn('id', $userIds);
//
//			/* Send a notification to each of those people if any
//			 * were actually found
//			 */
//			if ($allUsers->count()) {
//				$url = "/modules/eotc/status/modify/{$allEvents->first()->getId()}";
//
//				$msg = new Message(":someone changed the status for the EOTC event {$allEvents->first()->getNote()}",	// paramaterised, PDO-style
//					'/portrait.php?size=square32&userId='.$creator->getExternalId(),
//					$url
//				);
//
//				foreach($allUsers as $currentUser) {
//					$msgr->targetToUser($currentUser->getId(), $msg, "{$this->getMsgNamespaceSlug()}.{$metadata->getId()}.changed", Logger::INFO, array(
//						// map your params to values, if you use an array: 0 => label, 1 => href
//						'someone' => array($creator->formatName(), '/search/user/'.$creator->getId())
//					));
//
//					$this->chimp($currentUser->getFullname() . "\n");
//				}
//			}
//		}
//
//		return $msgr;
//	}

//	/**
//	 * Log stuff to a file due to asynchronous nature
//	 *
//	 * @param $message
//	 */
//	private function chimp($message) {
//		$filepointer = fopen('/vagrant/data/eotcmetadatastatus.log', 'a+');
//		fputs($filepointer, $message);
//		fclose($filepointer);
//	}

	/**
	 * Set msg namespace slug
	 *
	 * @param string $msgNamespaceSlug
	 */
	private function setMsgNamespaceSlug($msgNamespaceSlug) {
		$this->msgNamespaceSlug = $msgNamespaceSlug;
	}

	/**
	 * Get msg namespace slug
	 *
	 * @return string
	 */
	private function getMsgNamespaceSlug() {
		return $this->msgNamespaceSlug;
	}
}
