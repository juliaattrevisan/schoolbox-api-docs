<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;


use Monolog\Logger;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class ReturnedWorkNotifier extends AbstractModuleNotifier {

	const TRIGGER_RETURNED = 'returned';

	/** @var UserMapper */
	protected $userMapper;

	/** @var CategoryMapper */
	protected $catMapper;

	/** @var SubmissionBoxMapper */
	protected $boxMapper;


	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_DUEWORK;
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->userMapper = UserMapper::getInstance();
		$this->catMapper = CategoryMapper::getInstance();
		$this->boxMapper = SubmissionBoxMapper::getInstance();
		parent::loadDependencies($dependencies);
	}

	/**
	 * If created, raise a notification. No conditions, no other functions. That's it.
	 *
	 * @param TriggerBundle $bundle
	 * @throws ScheduleException
	 * @return void
	 */
	public function bundleTriggers($bundle) {

		/** @var ReturnedWork $mark */
		$mark = $bundle->getEvent()->getObject();

        // Confirmation of submission? Do nothing
        if ($mark->getIsConfirmationOfSubmission()) {
            return;
        }

		/** @var SubmissionBox $box */
		$box = $this->boxMapper->findById($mark->getSubmissionBox()->getId());

		if($box->getMarkVisibility() == SubmissionBox::MARK_VISIBILITY_PRIVATE) {
			// don't. even. bother.
			return;
		}

		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE: // when returned work is generated
			case Mapper::METHOD_UPDATE: // when returned work is updated:
				$bundle->attach(self::TRIGGER_RETURNED, self::IMMEDIATELY);
				break;

			case Mapper::METHOD_READ:
			case Mapper::METHOD_DELETE: // when returned work is...uh, un-returned...
				break;

			default:
				throw new ScheduleException("Unrecognised CRUD operation: '{$bundle->getMethod()}'.");
		}
	}


	/**
	 * @param SubmissionBox $box
	 * @return bool
	 */
	protected function parentCanView($box) {
		return $box->getMarkVisibility() >= SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT && !$this->conf->get('restrict_parent_duework_access');
	}

	/* ----------------------------------------------------------------------------------------------------------
	 *  ASYNC WORKER METHODS
	 * ---------------------------------------------------------------------------------------------------------- */

	/**
	 * @param string $target
	 * @param ReturnedWork $returnedWork
	 * @param User $user
	 * @param array|null $diff
	 * @return Messenger
	 */
	protected function generateMessage($target, $returnedWork, $user, $diff = null) {
		$msgr = new Messenger();

		/** @var User $student */
		$student = $this->userMapper->findById($returnedWork->getOwner()->getId());
		/** @var User $teacher */
		$teacher = $this->userMapper->findById($user->getId());
		/** @var SubmissionBox $box */
		$box = $this->boxMapper->findById($returnedWork->getSubmissionBox()->getId());
		/** @var Category $cat */
		$cat = $this->catMapper->findById($box->getCategory()->getId());

		$teacherId = $teacher->getId();
		$teacherExtId = $teacher->getExternalId();
		$teacherName = $teacher->formatName();

		$studentId = $student->getId();
		$studentName = $student->getFirstname();

		$boxId = $box->getId();
		$boxName = $box->getName();

		$catId = $cat->getId();
		$catName = $cat->getName();

		$icon = "/portrait?userId=$teacherExtId&size=square32";
		$href = "/submission/submissionHistory/$boxId/$studentId";

		// if student, 2nd-person language
		if($target == 'student' && $box->getMarkVisibility() >= SubmissionBox::MARK_VISIBILITY_STUDENT) {
			$msg = new Message(":teacher has marked your work for :boxname in :catname", $icon, $href);

			$params = array(
				'teacher' => array($teacherName, "/search/user/$teacherId"),
				'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
				'catname' => array($catName, "/homepage/$catId")
			);

			$msgr->targetToUser($studentId, $msg, "duework.$boxId.returned", Logger::NOTICE, $params);
		}

		// if parent, 3rd-person language
		elseif($target == 'parent' && $this->parentCanView($box)) {
			// If parent can't view results for this grade:
			// target to student's grades page
			if ($box->isMarkShownToParents()) {
				$href = "/submission/allWork/$studentId";
			}
			$msg = new Message(":teacher has marked :student's work for :boxname in :catname", $icon, $href);

			$params = array(
				'teacher' => array($teacherName, "/search/user/$teacherId"),
				'student' => array($studentName, "/search/user/$studentId"),
				'boxname' => array($boxName, "/submission/submissionHistory/$boxId"),
				'catname' => array($catName, "/homepage/$catId")
			);

			$msgr->targetToParent($studentId, $msg, "duework.$boxId.returned", Logger::NOTICE, $params);
		}

		return $msgr;
	}


	/**
	 * Notify a student that their work has been marked.
	 *
	 * @param ReturnedWork $returnedWork
	 * @param User $user
	 * @param array|null $diff
	 * @return Messenger
	 */
	public function notifyStudentReturned($returnedWork, $user, $diff = null) {
		return $this->generateMessage('student', $returnedWork, $user, $diff);
	}


	/**
	 * Notify a parent that their child's work has been marked.
	 *
	 * @param ReturnedWork $returnedWork
	 * @param User $user
	 * @param array|null $diff
	 * @return Messenger
	 */
	public function notifyParentReturned($returnedWork, $user, $diff = null) {
		return $this->generateMessage('parent', $returnedWork, $user, $diff);
	}

} 