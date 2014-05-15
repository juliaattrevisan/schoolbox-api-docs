<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component;


use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\Feed;
use Schoolbox\Feed\Item;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;

class OnlineAssessmentNotifier extends AbstractModuleNotifier implements SourceInterface {

	// TODO these two go elsewhere
	const TRIGGER_ATTEMPTED = 'attempted';
	const TRIGGER_MARKED    = 'marked';

	const TRIGGER_CLOSED    = 'closed';
	const TRIGGER_OPEN      = 'open';
	const TRIGGER_OVERDUE   = 'overdue';
	const TRIGGER_UPCOMING  = 'upcoming';
	const TRIGGER_UPDATED   = 'updated';

	// this should be configurable, but this will do for now
	const UPCOMING_WINDOW     = 2;

	/** @var CategoryMapper */
	protected $catMapper;

	/** @var AttemptMapper */
	protected $attemptMapper;

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_ONLINE_ASSESS;
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->catMapper = CategoryMapper::getInstance();
		$this->attemptMapper = AttemptMapper::getInstance();
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		/** @var OnlineAssessment $quiz */
		$quiz = $bundle->getEvent()->getObject();

		$open = $quiz->getStart()->getUnixTimestamp();
		$closed = $quiz->getFinish()->getUnixTimestamp();
		$upcoming = $closed - (self::UPCOMING_WINDOW * 3600);

		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE:
				$bundle->attachEach(array(
					self::TRIGGER_OPEN     => $open,
					self::TRIGGER_UPCOMING => $upcoming,
					self::TRIGGER_OVERDUE  => $closed,
					self::TRIGGER_CLOSED   => $closed
				));
				break;

			case Mapper::METHOD_UPDATE:

				// don't spawn/replace an open-on-replace if it's in the past
				if($open > time()) {
					$bundle->attach(self::TRIGGER_OPEN, $open);
				}

				$bundle->attachEach(array(
					self::TRIGGER_UPDATED  => self::IMMEDIATELY,
					self::TRIGGER_UPCOMING => $upcoming,
					self::TRIGGER_OVERDUE  => $closed,
					self::TRIGGER_CLOSED   => $closed
				));

				break;

			case Mapper::METHOD_DELETE:
				$bundle->detachEach(array(
					self::TRIGGER_OPEN,
					self::TRIGGER_UPCOMING,
					self::TRIGGER_OVERDUE,
					self::TRIGGER_CLOSED,
				));
				break;
		}

	}


	/**
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context - most likely a category ID or ignored
	 * @param string $roletype - the roletype of the user for whom this feed is being retrieved
	 * @param int $limit - how many messages to retrieve
	 * @param int $offset - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $roletype, $limit = self::SOFT_LIMIT, $offset = 0) {
		$c = new Collection();

		// if parents aren't allowed to see due work, return an empty collection
		if($roletype == Role::PARENT && !$this->conf->get('restrict_parent_duework_access')) {
			return $c;
		}

		/** @var OnlineAssessment[]|Collection $quizzes */
		$quizzes = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($quizzes as $quiz) {
			$c->merge(Feed::getItemsFromMessenger($this->notifyStudentOpen($quiz, $quiz->getUser()), $quiz->getStart()->getUnixTimestamp()));
			$c->merge(Feed::getItemsFromMessenger($this->notifyFeedClosed($quiz, $quiz->getUser()), $quiz->getStart()->getUnixTimestamp()));
		}

		return $c;
	}


	/**
	 * Returns the metadata pertaining to this source.
	 * Useful to RSS
	 *
	 * @return mixed
	 */
	public function getChannelMetadata() {
		// not implemented
		return null;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @return User[]|Collection
	 */
	public function getStudentsUnattemptedList($quiz) {
		$userCollection = UserAccessMapper::getInstance()->findStudentsInCategory($quiz->getCategory());

		$attemptCollection = $this->attemptMapper->findByColumnList(array(
			'assess_id' => $quiz->getId(),
		));

		$attemptedCollection = new Collection($attemptCollection->getColumnValues('user'));
		$attemptedIdArray = $attemptedCollection->getColumnValues('id');

		$unattemptedCollection = new Collection();

		foreach($userCollection as $user) {
			if(!in_array($user->getId(), $attemptedIdArray)) {
				$unattemptedCollection->add($user);
			}
		}

		return $unattemptedCollection;
	}


	/* ASYNC METHODS */

	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffClosed($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentOpen($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentOverdue($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentOverdue($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @return Messenger
	 * @param array $diff
	 */
	public function notifyStudentUpcoming($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentUpcoming($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentUpdated($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


	/**
	 * Notify the feed of box closures
	 *
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyFeedClosed($quiz, $owner, $diff = null) {
		$msgr = new Messenger();


		return $msgr;
	}


}