<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component;


use Alaress_Date_Format;
use Alaress_Date_Relative;
use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\MessageFactory;
use Schoolbox\Notif\Message\MessageCollection;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class SubmissionBoxNotifier extends AbstractModuleNotifier {

	// list of triggers that can generate notifications

	const TRIGGER_OPEN        = 'open';
	const TRIGGER_UPDATED     = 'updated';
	const TRIGGER_UPCOMING    = 'upcoming';
	const TRIGGER_OVERDUE     = 'overdue';
	const TRIGGER_CLOSED      = 'closed';

	// TODO these 3 will go in their own notifiers
//	const TRIGGER_SUBMITTED   = 'submitted';
//	const TRIGGER_RESUBMITTED = 'resubmitted';
//	const TRIGGER_MARKED      = 'marked';

	// this should be configurable, but this will do for now
	const UPCOMING_WINDOW     = 2;


	/** @var SubmissionBoxMapper */
	protected $submissionBoxMapper;

	/** @var UserMapper */
	protected $userMapper;

	/** @var SubmissionMapper */
	protected $submissionMapper;

	/** @var CategoryMapper */
	protected $categoryMapper;

	/** @var RoleMapper */
	protected $roleMapper;

	/** @var \User */
	protected $_DEPRECATED_USER_HANDLER;

	/** @var \External_Class */
	protected $_DEPRECATED_EXTERNAL_CLASS_HANDLER;


	/**
	 * @param IConfigLoader $configLoader
	 * @param MessageFactory $messageFactory
	 */
	public function __construct($configLoader, $messageFactory) {
		parent::__construct($configLoader, $messageFactory);

		$this->submissionBoxMapper = SubmissionBoxMapper::getInstance();
		$this->userMapper = UserMapper::getInstance();
		$this->submissionMapper = SubmissionMapper::getInstance();
		$this->categoryMapper = CategoryMapper::getInstance();
		$this->roleMapper = RoleMapper::getInstance();

		$this->_DEPRECATED_USER_HANDLER = new \User();
		$this->_DEPRECATED_EXTERNAL_CLASS_HANDLER = new \External_Class();

	}


	/**
	 * Generate a set of triggers to be fired at specific times, based on the given data.
	 *
	 * @param string $method                the CRUD operation that was performed on the object
	 * @param SubmissionBox $submissionBox  the Mappable object that was operated on
	 * @throws ScheduleException            will be thrown if the CRUD operation is not recognised
	 * @return Alaress_Date_Format[]|int[]  return an array of ADFs or timestamps, indexed by trigger label
	 */
	public function generateTriggers($method, $submissionBox) {

		// unsure if this clones the SubmissionBox or the Alaress_Date_Format, but in this use-case it doesn't matter
		$upcoming = clone $submissionBox->getDueDate();
		$upcoming->addHours(-1 * self::UPCOMING_WINDOW);

		$events = array();

		switch($method) {
			case Mapper::METHOD_UPDATE:
				$events[self::TRIGGER_UPDATED] = self::IMMEDIATELY;
				// intentional fallthrough; no break

			case Mapper::METHOD_CREATE:
				$events[self::TRIGGER_OPEN]     = $submissionBox->getOpenDate();
				$events[self::TRIGGER_OVERDUE]  = $submissionBox->getDueDate();
				$events[self::TRIGGER_UPCOMING] = $upcoming;
				$events[self::TRIGGER_CLOSED]   = $submissionBox->getLateDate();
				break;

			case Mapper::METHOD_READ:
				break;

			case Mapper::METHOD_DELETE:
				break;

			default:
				throw new ScheduleException("Unrecognised CRUD operation: '$method'.");
		}

		return $events;
	}


	/**
	 * Generate a set of events to be scheduled by retrieving a backlog from the database
	 *
	 * @return Alaress_Date_Format[]|int[]
	 */
	public function spoolTriggersFromDatabase() {
		return array();
	}


	/**
	 * @param SubmissionBox $submissionBox
	 * @return User[]|Collection
	 */
	protected function getStudentsUnsubmittedList($submissionBox) {

		// just fyi, make sure this has been hydrated
		$cat = $submissionBox->getCategory();

		// but first, MAGIC!
		$this->categoryMapper->getExternalUser()->getCurrentSemester();

		// TODO. This much code to get a classlist is ridiculous. Find an appropriate Mapper, refactor this into there.

		// start with a list of students in this category
		$studentArray = $this->_DEPRECATED_EXTERNAL_CLASS_HANDLER->getStudents($cat->getCodeString()) ?: array();
		$memberArray = $this->_DEPRECATED_USER_HANDLER->getAllInCategory($cat->getId()) ?: array();

		$synergyIdArray = array();

		foreach($studentArray as $array) {
			$synergyIdArray[$array['synergy_id']] = $array['synergy_id'];
		}

		foreach($memberArray as $array) {
			$synergyIdArray[$array['synergy_id']] = $array['synergy_id'];
		}

		$userCollection = $this->userMapper->findByColumnList(array(
			'synergy_id' => array_values($synergyIdArray),
			'group_id'   => $this->roleMapper->findByColumn('group_type', Role::STUDENT)->getColumnValues('id')
		));

		// phew! we've got the classlist. Now we have to do a MINUS against students who HAVE submitted.

		$submissionCollection = $this->submissionMapper->findByColumnList(array(
			'box_id' => $submissionBox->getId()
		));

		// extract the owner column from the submission collection returned
		$submittedCollection = new Collection($submissionCollection->getColumnValues('owner'));
		$submittedIdArray = $submittedCollection->getColumnValues('id');

		$unsubmittedCollection = new Collection();

		foreach($userCollection as $user) {
			/** @var User $user */
			if(!in_array($user->getId(), $submittedIdArray)) {
				$unsubmittedCollection->add($user);
			}
		}

		return $unsubmittedCollection;

	}


	/* TRIGGER EVENTS */

	/**
	 * Student notified when SubmissionBox opens.
	 *
	 * Returns an array where the keys are the messages to send, and the values
	 * are arrays of topic on which those messages are to be published.
	 *
	 * @var SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return MessageCollection
	 */
	public function notifyStudentOpen($submissionBox, $user, $diff = null) {
		// yep. self-hydration.
		$submissionBox = $this->submissionBoxMapper->findById($submissionBox->getId());
		$boxId = $submissionBox->getId();
		$boxType = $submissionBox->getWorkType()->getName();
		$boxName = $submissionBox->getName();

		$authorId = $user->getId();
		$authorName = $user->formatName();
		$authorExtId = $user->getExternalId();

		$cat = $submissionBox->getCategory();
		$catId = $cat->getId();
		$catName = $cat->getName();

		$indefArticle = in_array(strtolower($boxType[0]), array('a','e','i','o','u')) ? 'an' : 'a';

		$msg = new Message(
			":username opened $indefArticle $boxType, :boxname, in :catname",
			"/portrait?userId=$authorExtId&size=square32", "/submission/submissionHistory/$boxId"
		);

		$params = array(
			'username' => array($authorName, "/search/user/$authorId"),
			'boxname'  => array($boxName, "/submission/submissionHistory/$boxId"),
			'catname'  => array($catName, "/homepage/$catId")
		);

		$mCollection = new MessageCollection();

		$topic = $this->topics->targetCategoryStudents($cat->getId());

		$mCollection->bind($topic, Logger::NOTICE, $msg, $params);

		return $mCollection;
	}


	/**
	 * Student notified when a SubmissionBox is updated
	 *
	 * @param SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return MessageCollection
	 */
	public function notifyStudentUpdated($submissionBox, $user, $diff = null) {
		$mCollection = new MessageCollection();

		// discard all update notifications if the submissionbox isn't open
		$openStamp = $submissionBox->getOpenDate()->getUnixTimestamp();
		$closeStamp = $submissionBox->getDueDate()->getUnixTimestamp();
		$now = time();

		if($openStamp > $now || $closeStamp < $now) {
			return $mCollection;
		}

		// if there's no actual difference
		if(!count($diff)) {
			return $mCollection;
		}

		$boxId = $submissionBox->getId();
		$boxName = $submissionBox->getName();

		$cat = $submissionBox->getCategory();
		$catId = $cat->getId();

		$authorId = $user->getId();
		$authorExtId = $user->getExternalId();
		$authorName = $user->formatName();

		$icon = "/portrait?userId=$authorExtId&size=square32";
		$href = "/submission/submissionHistory/$boxId";

		/* use the diff to establish what changed */


		// renamed
		if(isset($diff['name'])) {

			// tell everyone in this category
			$topic = $this->topics->targetCategory($cat->getId());

			// parameterised text, link and icon
			$msg = new Message(":author renamed :oldname to :newname in :catname", $icon, $href);

			// params for this instance - oldname doesn't have an associated href
			$params = array(
				'author'  => array($authorName, "/search/user/$authorId"),
				'oldname' => $diff['name'][1],
				'newname' => array($diff['name'][0], "/submission/submissionHistory/$boxId"),
				'catname' => array($cat->getName(), "/homepage/$catId")
			);

			// send internally to all concerned
			$mCollection->bind($topic, Logger::NOTICE, $msg, $params);

			unset($diff['name']);
		}



		// extended or shortened
		if(isset($diff['dueDate'])) {
			// strtotime is safe here because dates are in 'YYYY-MM-DD H:i:s' format
			$oldDate = strtotime($diff['dueDate'][1]);
			$newDate = strtotime($diff['dueDate'][0]);

			$verb = $newDate > $oldDate ? 'extended' : 'changed the due date of';
			$relative = new Alaress_Date_Relative($newDate);
			$dateformat = ((int)$relative->getUsingFormat('Hi') == '1159' ? 'g:ia \o\n ' : '') . 'D, jS M';


			$msg = new Message(":author $verb :boxname to :date in :catname", $icon, $href);

			$topic = $this->topics->targetCategory($cat->getId());

			$mCollection->bind($topic, Logger::WARNING, $msg, array(
				'author' => array($authorName, "/search/user/$authorId"),
				'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
				'date' => $dateformat,
				'catname' => array($cat->getName(), "/homepage/$catId")
			));

			unset($diff['dueDate']);
		}



		// changed accepting online submissions
		if(isset($diff['acceptOnline'])) {
			if($submissionBox->getAcceptOnline()) {
				$m = "You no longer need to submit online for :boxname in :catname";
			}
			else {
				$m = "You now need to submit online for :boxname in :catname";
			}

			$msg = new Message($m, $icon, $href);

			// send this to students only, other staff don't need to see it
			$topic = $this->topics->targetCategoryStudents($cat->getId());

			$mCollection->bind($topic, Logger::NOTICE, $msg, array(
				'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
				'catname' => array($cat->getName(), "/homepage/$catId")
			));

			unset($diff['acceptOnline']);
		}



		// if none of the above apply, but there are changes, just be generic
		if(count($diff) && !$mCollection->hasMessages()) {
			$msg = new Message(":author updated :boxname in :catname", $icon, $href);

			$mCollection->bind($this->topics->targetCategory($cat->getId()), Logger::NOTICE, $msg, array(
				'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
				'catname' => array($cat->getName(), "/homepage/$catId")
			));
		}

		return $mCollection;
	}


	/**
	 * Student notified when a SubmissionBox is closing AND they have not submitted
	 *
	 * @param SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return MessageCollection
	 */
	public function notifyStudentUpcoming($submissionBox, $user, $diff = null) {
		$mCollection = new MessageCollection();

		// don't raise notifications if this box doesn't accept online submissions
		if(!$submissionBox->getAcceptOnline()) {
			return $mCollection;
		}

		$boxId = $submissionBox->getId();
		$boxName = $submissionBox->getName();

		$cat = $this->categoryMapper->findById($submissionBox->getCategory()->getId());
		$catId = $cat->getId();
		$catName = $cat->getName();

		$icon = '?';
		$href = "/submission/submissionHistory/$boxId";

		$when = self::UPCOMING_WINDOW . " hour" . self::UPCOMING_WINDOW == 1 ? '' : 's';
		$msg = new Message(":boxname is due in $when in :catname", $icon, $href);

		$unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

		$topicArray = array();
		foreach($unsubmittedStudents as $user) {
			$topicArray[] = $this->topics->targetUser($user->getId());
		}

		$mCollection->bind($topicArray, Logger::WARNING, $msg, array(
			'boxname' => array($boxName, $href),
			'catname' => array($catName, "/homepage/$catId"),
		));

		return $mCollection;
	}


	/**
	 * Parent notified when a SubmissionBox is closing AND their child has not submitted
	 *
	 * @param SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return MessageCollection
	 */
	public function notifyParentUpcoming($submissionBox, $user, $diff = null) {
		$mCollection = new MessageCollection();

		// don't raise notifications if this box doesn't accept online submissions
		if(!$submissionBox->getAcceptOnline()) {
			return $mCollection;
		}

		$boxName = $submissionBox->getName();
		$boxId = $submissionBox->getId();
		// hyyydrate
		$cat = $this->categoryMapper->findById($submissionBox->getCategory()->getId());
		$submissionBox->setCategory($cat);

		$catId = $submissionBox->getCategory()->getId();
		$catName = $submissionBox->getCategory()->getName();

		$icon = '?';
		$href = "/submission/submissionHistory/$boxId";

		$msg = new Message(":child has not yet submitted work for :boxname in :catname", $icon, $href);

		// get a list of students who haven't submitted, and get their parents too
		$unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

		foreach($unsubmittedStudents as $student) {
			$childName = $student->getFirstname();
			$childId = $student->getId();

			$topic = $this->topics->targetParentsOfStudent($student->getId());

			$mCollection->bind($topic, Logger::WARNING, $msg, array(
				'child'     => array($childName, "/search/user/$childId"),
				'boxname'   => array($boxName, "/submission/submissionHistory/$boxId"),
				'catname'   => array($catName, "/homepage/$catId")
			));
		}

		return $mCollection;
	}


	/**
	 * Student notified when a SubmissionBox is overdue AND they have not submitted
	 *
	 * @param SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return array
	 */
	public function notifyStudentOverdue($submissionBox, $user, $diff = null) {
		$mCollection = new MessageCollection();

		// don't raise notifications if this box doesn't accept online submissions
		if(!$submissionBox->getAcceptOnline()) {
			return $mCollection;
		}

		// if the late date has passed, ignore this message
		if($submissionBox->getLateDate()->getUnixTimestamp() > time()) {
			return $mCollection;
		}

		$boxId = $submissionBox->getId();
		$boxName = $submissionBox->getName();

		$cat = $this->categoryMapper->findById($submissionBox->getCategory()->getId());
		$catId = $cat->getId();
		$catName = $cat->getName();

		$icon = '?';
		$href = "/submission/submissionHistory/$boxId";

		$msg = new Message(":boxname is now overdue in :catname", $icon, $href);

		$unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

		$topicArray = array();
		foreach($unsubmittedStudents as $user) {
			$topicArray[] = $this->topics->targetUser($user->getId());
		}

		$mCollection->bind($topicArray, Logger::ERROR, $msg, array(
			'boxname' => array($boxName, $href),
			'catname' => array($catName, "/homepage/$catId"),
		));

		return $mCollection;
	}


	/**
	 * Parent notified when a SubmissionBox is overdue AND their child has not submitted
	 *
	 * @param SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return array
	 */
	public function notifyParentOverdue($submissionBox, $user, $diff = null) {
		$mCollection = new MessageCollection();

		// don't raise notifications if this box doesn't accept online submissions
		if(!$submissionBox->getAcceptOnline()) {
			return $mCollection;
		}

		$boxName = $submissionBox->getName();
		$boxId = $submissionBox->getId();
		// hyyydrate
		$cat = $this->categoryMapper->findById($submissionBox->getCategory()->getId());
		$submissionBox->setCategory($cat);

		$catId = $submissionBox->getCategory()->getId();
		$catName = $submissionBox->getCategory()->getName();

		$icon = '?';
		$href = "/submission/submissionHistory/$boxId";

		$msg = new Message(":child did not submit work for :boxname in :catname", $icon, $href);

		// get a list of students who haven't submitted, and get their parents too
		$unsubmittedStudents = $this->getStudentsUnsubmittedList($submissionBox);

		foreach($unsubmittedStudents as $student) {
			$childName = $student->getFirstname();
			$childId = $student->getId();

			$topic = $this->topics->targetParentsOfStudent($student->getId());

			$mCollection->bind($topic, Logger::ERROR, $msg, array(
				'child'     => array($childName, "/search/user/$childId"),
				'boxname'   => array($boxName, "/submission/submissionHistory/$boxId"),
				'catname'   => array($catName, "/homepage/$catId")
			));
		}

		return $mCollection;
	}


	/**
	 * Staff notified when a SubmissionBox late-date has passed AND students did not submit
	 *
	 * @param SubmissionBox $submissionBox
	 * @param User $user
	 * @param array $diff
	 * @return array
	 */
	protected function notifyStaffClosed($submissionBox, $user, $diff = null) {
		$mCollection = new MessageCollection();

		// don't raise notifications if this box doesn't accept online submissions
		if(!$submissionBox->getAcceptOnline()) {
			return $mCollection;
		}

		$totalUnsubmitted = $this->getStudentsUnsubmittedList($submissionBox)->count();

		// everyone submitted! woohoo!
		if($totalUnsubmitted == 0) {
			return $mCollection;
		}

		$boxId = $submissionBox->getId();

		$icon = '?';
		$href = "/submission/viewSubmission/$boxId";

		$msg = new Message(":boxname has closed and :number did not submit work", $icon, $href);

		$mCollection->bind($this->topics->targetUser($user->getId()), Logger::ERROR, $msg, array(
			'boxname' => array($submissionBox->getName(), "/submission/viewSubmission/$boxId"),
			'number'  => sprintf("%s student%s", $totalUnsubmitted, $totalUnsubmitted == 1 ? '' : 's')
		));

		return $mCollection;
	}


	/* TODO THESE NOTIFICATIONS BELONG IN DIFFERENT NOTIFIERS */

//	/**
//	 * Staff notified when a student submits to a SubmissionBox
//	 *
//	 * @param SubmissionBox $submissionBox
//	 * @param User $user
//	 * @param array $diff
//	 * @return array
//	 */
//	public function notifyStaffSubmitted($submissionBox, $user, $diff = null) {
//
//	}
//
//
//	/**
//	 * Staff notified when a student resubmits to a SubmissionBox
//	 *
//	 * @param SubmissionBox $submissionBox
//	 * @param User $user
//	 * @param array $diff
//	 * @return array
//	 */
//	protected function notifyStaffResubmitted($submissionBox, $user, $diff = null) {
//
//	}
//
//
//	/**
//	 * Student notified when a teacher returns a mark to them
//	 *
//	 * @param SubmissionBox $submissionBox
//	 * @param User $user
//	 * @param array $diff
//	 * @return array
//	 */
//	public function notifyStudentMarked($submissionBox, $user, $diff = null) {
//
//	}

}