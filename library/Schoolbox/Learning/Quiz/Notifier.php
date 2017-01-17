<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Quiz;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Mapper as BaseMapper;

/**
 * @method Mapper getMapper()
 */
class Notifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_CLOSED    = 'closed';
	const TRIGGER_OPEN      = 'open';
	const TRIGGER_OVERDUE   = 'overdue';
	const TRIGGER_UPCOMING  = 'upcoming';
	const TRIGGER_UPDATED   = 'updated';

	// this should be configurable, but this will do for now
	const UPCOMING_WINDOW     = 2;

	/** @var FolderMapper */
	protected $categoryMapper;

	/** @var QuizAttemptMapper */
	protected $attemptMapper;

	/** @var WorkTypeMapper */
	protected $workTypeMapper;

	/** @var UserAccessMapper */
	protected $userAccessMapper;

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
		$this->categoryMapper = FolderMapper::getInstance();
		$this->attemptMapper = QuizAttemptMapper::getInstance();
		$this->workTypeMapper = WorkTypeMapper::getInstance();
		$dependencies['userAccessMapper'] = isset($dependencies['userAccessMapper'])
			? $dependencies['userAccessMapper']
			: UserAccessMapper::getInstance();
		parent::loadDependencies($dependencies);
	}


	/**
	 * @param  UserAccessMapper $userAccessMapper
	 * @return $this
	 */
	public function setUserAccessMapper($userAccessMapper)
	{
		$this->userAccessMapper = $userAccessMapper;
		return $this;
	}


	/**
	 * Fully hydrates the quiz object, in this case with the work type and
	 * category.
	 *
	 * @todo Remove this when mapper findBy methods can specify joins to hydrate
	 *
	 * @param  Model $quiz
	 * @return Model
	 */
	protected function fullyHydrateQuiz(Model $quiz) {
		// @mattday I've got a surprise for you - it's poop!
		$quiz = $this->getMapper()->findById($quiz->getId());
		$quiz
			->setWorkType($this->workTypeMapper->findById($quiz->getWorkType()->getId()))
			->setCategory($this->categoryMapper->findById($quiz->getCategory()->getId()))
		;
		return $quiz;
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		/** @var Model $quiz */
		$quiz = $bundle->getEvent()->getObject();

        // the mere fact that this is required is ludicrous
        if (!($quiz->getStart() && $quiz->getFinish())) {
            $quiz = $this->fullyHydrateQuiz($quiz);
        }

		$open = $quiz->getStart()->getUnixTimeStamp();
		$closed = $quiz->getFinish()->getUnixTimeStamp();
		$upcoming = $closed - (self::UPCOMING_WINDOW * 3600);

		switch($bundle->getMethod()) {
			case BaseMapper::METHOD_CREATE:
				$bundle->attachEach(array(
					self::TRIGGER_OPEN     => $open,
					self::TRIGGER_UPCOMING => $upcoming,
					self::TRIGGER_OVERDUE  => $closed,
					self::TRIGGER_CLOSED   => $closed
				));
				break;

			case BaseMapper::METHOD_UPDATE:

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

			case BaseMapper::METHOD_DELETE:
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
	 * Generate n Instances, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context     - most likely a category ID or ignored
	 * @param string $roletype - the roletype of the user for whom this feed is being retrieved
	 * @param int $limit       - how many messages to retrieve
	 * @param int $offset      - by default, most recent
	 * @return Collection|Instance[]
	 */
	public function getFeed($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0) {
		$c = new Collection();

		// if parents aren't allowed to see due work, return an empty collection
		if($roletype == Role::PARENT && !$this->conf->get('restrict_parent_duework_access')) {
			return $c;
		}

		if(!$this->mapper instanceof FeedableMapperInterface) {
			return $c;
		}

		/** @var Model[]|Collection $quizzes */
		$quizzes = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($quizzes as $quiz) {
            $c = $c->merge(new Collection($this->notifyStudentOpen($quiz, $quiz->getUser())->getAll($quiz->getStart()->getUnixTimeStamp())));
            $c = $c->merge(new Collection($this->notifyFeedClosed($quiz, $quiz->getUser())->getAll($quiz->getFinish()->getUnixTimeStamp())));
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
	 * @param Model $quiz
	 * @return User[]|Collection
	 */
	public function getStudentsUnattemptedList($quiz) {
		$userCollection = $this->userAccessMapper->findStudentsInCategory($quiz->getCategory());

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
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffClosed($quiz, $owner, $diff = null) {
        $envelope = new Envelope();

		$quiz = $this->fullyHydrateQuiz($quiz);
		$totalUnattempted = $this->getStudentsUnattemptedList($quiz)->count();

		// everyone submitted! woohoo!
		if($totalUnattempted == 0) {
			return $envelope;
		}

		$quizId   = $quiz->getId();
		$quizName = $quiz->getTitle();

		$icon = "/images/icon.php?size={$this->iconSize}&icon=work";
		$href = "/assess/results/$quizId";

        $bob = new Builder();
        $bob->addTopic("assess.$quizId.closed")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->setTitle(":quizname has closed and :number did not submit work")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('quizname', new Param($quizName, $href))
            ->setParam('number', new Param(sprintf("%s student%s", $totalUnattempted, $totalUnattempted == 1 ? '' : 's')));

        $envelope->broadcastToCategory($quiz->getCategory(), UserAccess::ACCESS_WRITE, $bob->build());
        return $envelope;
	}


	/**
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentOpen($quiz, $owner, $diff = null) {

        $envelope = new Envelope();

        if($this->conf->get('restrict_parent_duework_access')) {
            return $envelope;
        }

        $quizId = $quiz->getId();
        $quiz   = $this->fullyHydrateQuiz($quiz);

        $quizType = $quiz->getWorkType()->getName();
        $quizName = $quiz->getTitle();

        $icon = "/portrait?userId={$owner->getExternalId()}&size={$this->iconSize}";
        $href = "/assess/intro/$quizId";

        $indefArticle = in_array(strtolower($quizType[0]), array('a','e','i','o','u')) ? 'an' : 'a';

        $bob = new Builder();
        $bob->addTopic("assess.$quizId.upcoming")
            ->addFilter('roleType', [Role::STUDENT])
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::WARNING)
            ->setTitle(":username opened $indefArticle $quizType, :quizname, in :catname")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('username', new UserParam($owner))
            ->setParam('quizname', new Param($quizName, "/assess/intro/$quizId"))
            ->setParam('catname', new CategoryParam($quiz->getCategory()));

        $envelope->broadcastToCategory($quiz->getCategory(), UserAccess::ACCESS_READ, $bob->build());
        return $envelope;
	}


	/**
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentOverdue($quiz, $owner, $diff = null) {
        $envelope = new Envelope();

        if ($this->conf->get('restrict_parent_duework_access')) {
            return $envelope;
        }

        $quizId = $quiz->getId();
        $quiz   = $this->fullyHydrateQuiz($quiz);

        $quizName = $quiz->getTitle();

        $icon = "/images/icon.php?size={$this->iconSize}&icon=work";
        $href = "/assess/intro/$quizId";

        $bob = new Builder();
        $bob->addTopic("assess.$quizId.overdue")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->setTitle(":quizname has closed in :catname")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('quizname', new Param($quizName, $href))
            ->setParam('catname', new CategoryParam($quiz->getCategory()));

        $unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

        foreach($unattemptedStudents as $student) {
            $bob->setParam('child', new UserParam($student));
            $envelope->targetToUser($student->getId(), $bob->build());
        }

        return $envelope;

//		$msgr = new Messenger();
//
//		$quizId = $quiz->getId();
//		$quiz   = $this->fullyHydrateQuiz($quiz);
//
//		$quizName = $quiz->getTitle();
//
//		$cat     = $quiz->getCategory();
//		$catId   = $cat->getId();
//		$catName = $cat->getName();
//
//		$icon = "/images/icon.php?size={$this->iconSize}&icon=work";
//		$href = "/assess/intro/$quizId";
//
//		$msg = new Message(":quizname has closed in :catname", $icon, $href);
//
//		$unattemptedStudents = $this->getStudentsUnattemptedList($quiz);
//
//		foreach($unattemptedStudents as $user) {
//			$msgr->targetToUser($user->getId(), $msg, "assess.$quizId.overdue", Logger::ERROR, array(
//				'quizname' => array($quizName, $href),
//				'catname'  => array($catName, "/homepage/$catId"),
//			));
//		}
//
//		return $msgr;
	}


	/**
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentOverdue($quiz, $owner, $diff = null) {
//		$msgr = new Messenger();
		$envelope = new Envelope();

		if($this->conf->get('restrict_parent_duework_access')) {
//			return $msgr;
			return $envelope;
		}

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

//		$cat     = $quiz->getCategory();
//		$catId   = $cat->getId();
//		$catName = $cat->getName();

		$icon = "/images/icon.php?size={$this->iconSize}&icon=work";
		$href = "/assess/intro/$quizId";

		$bob = new Builder();
        $bob->addTopic("assess.$quizId.overdue")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->setTitle(":child did not attempt :quizname in :catname")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('quizname', new Param($quizName, $href))
            ->setParam('catname', new CategoryParam($quiz->getCategory()));

//		$msg = new Message(":child did not attempt :quizname in :catname", $icon, $href);

		$unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

		foreach($unattemptedStudents as $student) {
            $bob->setParam('child', new UserParam($student));
            $envelope->targetToParent($student->getId(), $bob->build());

//			$childName = $student->getFirstname();
//			$childId = $student->getId();
//
//			$msgr->targetToParent($childId, $msg, "assess.$quizId.overdue", Logger::ERROR, array(
//				'child'    => array($childName, "/search/user/$childId"),
//				'quizname' => array($quizName, $href),
//				'catname'  => array($catName, "/homepage/$catId"),
//			));
		}


//		return $msgr;
        return $envelope;
	}


	/**
	 * @param Model $quiz
	 * @param User $owner
	 * @return Envelope
	 * @param array $diff
	 */
	public function notifyStudentUpcoming($quiz, $owner, $diff = null) {

        $envelope = new Envelope();

        if($this->conf->get('restrict_parent_duework_access')) {
            return $envelope;
        }

        $quizId = $quiz->getId();
        $quiz   = $this->fullyHydrateQuiz($quiz);

        $quizName = $quiz->getTitle();

        $icon = "/images/icon.php?size={$this->iconSize}&icon=work";
        $href = "/assess/intro/$quizId";

        $when = self::UPCOMING_WINDOW . " hour" . ( self::UPCOMING_WINDOW == 1 ? '' : 's' );

        $bob = new Builder();
        $bob->addTopic("assess.$quizId.upcoming")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::WARNING)
            ->setTitle(":quizname is due in $when in :catname")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('quizname', new Param($quizName, "/assess/intro/$quizId"))
            ->setParam('catname', new CategoryParam($quiz->getCategory()));

        $unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

        foreach($unattemptedStudents as $student) {
            $bob->setParam('child', new UserParam($student));
            $envelope->targetToUser($student->getId(), $bob->build());
        }

        return $envelope;
	}


	/**
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentUpcoming($quiz, $owner, $diff = null) {
        $envelope = new Envelope();

        if($this->conf->get('restrict_parent_duework_access')) {
            return $envelope;
        }

        $quizId = $quiz->getId();
        $quiz   = $this->fullyHydrateQuiz($quiz);

        $quizName = $quiz->getTitle();

        // don't provide a link for parents to click on if it's just going to give the a 403
        $quizParam = $this->conf->get('restrict_student_submissions')
            ? new Param($quizName)
            : new Param($quizName, "/submission/submissionHistory/$quizId");

        $icon = "/images/icon.php?size={$this->iconSize}&icon=work";
        $href = "/assess/intro/$quizId";

        $bob = new Builder();
        $bob->addTopic("assess.$quizId.upcoming")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::WARNING)
            ->setTitle(":child has not yet attempted :quizname in :catname")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('quizname', $quizParam)
            ->setParam('catname', new CategoryParam($quiz->getCategory()));

        $unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

        foreach($unattemptedStudents as $student) {
            $bob->setParam('child', new UserParam($student));
            $envelope->targetToParent($student->getId(), $bob->build());
        }

        return $envelope;
	}


	/**
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentUpdated($quiz, $owner, $diff = null) {
		$msgr = new Messenger();

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		// discard all update notifications if the quiz isn't open *EXCEPT mark visibility changes*
		if (empty($diff['markVisibility'])) {
			$openStamp = $quiz->getStart()->getUnixTimeStamp();
			$closeStamp = $quiz->getFinish()->getUnixTimeStamp();
			$now = time();

			if($openStamp > $now || $closeStamp < $now) {
				return $msgr;
			}
		}

		// if there's no actual difference
		if(!count($diff)) {
			return $msgr;
		}

		$quizName = $quiz->getTitle();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$ownerId    = $owner->getId();
		$ownerName  = $owner->formatName();

		$icon = "/images/icon.php?size={$this->iconSize}&icon=work";
		$href = "/assess/intro/$quizId";


		// renamed
		if(isset($diff['title'])) {

			$msg = new Message(":author renamed :oldname to :newname in :catname", $icon, $href);

			// params for this instance - oldname doesn't have an associated href
			$params = array(
				'author'  => array($ownerName, "/search/user/$ownerId"),
				'oldname' => $diff['title'][1],
				'newname' => array($diff['title'][0], "/assess/intro/$quizId"),
				'catname' => array($catName, "/homepage/$catId")
			);

			$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.renamed", Logger::NOTICE, $params,
				array('roletype' => Role::STUDENT)
			);

			unset($diff['title']);
		}


		// extended or brought forward
		if(isset($diff['finish'])) {
			// strtotime is safe here because dates are in 'YYYY-MM-DD H:i:s' format
			$oldDate = strtotime($diff['finish'][1]);
			$newDate = strtotime($diff['finish'][0]);

			$verb = $newDate > $oldDate ? 'extended' : 'changed the closing date of';
			$relative = new Alaress_Date_Format($newDate, 'U');


            $date = $relative->getLongDateOnly();
            if(!$relative->isAtEndOfDay()) {
                $date = $relative->getTimeOnly() .' on '.$date;
            }

			$msg = new Message(":author $verb :quizname to :date in :catname", $icon, $href);

			$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.datechanged", Logger::NOTICE, array(
					'author' => array($ownerName, "/search/user/$ownerId"),
					'quizname' => array($quizName, "/assess/intro/$quizId"),
					'date' => $date,
					'catname' => array($cat->getName(), "/homepage/$catId")
				),
				array(
					'roletype' => Role::STUDENT
				)
			);

			unset($diff['finish']);
		}

		// INCREASE in mark visibility (quiz can be finished in this instance)
		// doesn't actually create messages, just triggers for others to be created
		if (isset($diff['markVisibility'])) {
			list($new, $old) = $diff['markVisibility'];
			if ($new > $old) {
				// for anyone who has been marked, raise the assess.marked notification
				/** @var Collection|QuizAttempt[] $attempts */
				$attempts = $this->attemptMapper->findByColumn('assess_id', $quiz->getId());
				foreach ($attempts as $attempt) {
					$this->raiseNotification($quiz->getUser(), BaseMapper::METHOD_UPDATE, $attempt, $this->attemptMapper);
				}
			}
			unset($diff['markVisibility']);
		}



		// if none of the above apply, but there are changes, just be generic
		if(count($diff) && $msgr->isEmpty()) {
			$msg = new Message(":author updated :quizname in :catname", $icon, $href);

			$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.updated", Logger::NOTICE,
				array(
					'author' => array($ownerName, "/search/user/$ownerId"),
					'quizname' => array($quizName, "/assess/intro/$quizId"),
					'catname' => array($cat->getName(), "/homepage/$catId")
				),
				array(
					'roletype' => Role::STUDENT
				)
			);
		}

		return $msgr;
	}

    /**
     * @param Model $quiz
     * @param User $owner
     * @param array $diff
     * @return Messenger
     */
    public function notifyParentUpdated($quiz, $owner, $diff = null) {
        $msgr = new Messenger();

        if ($this->conf->get('restrict_parent_duework_access')) {
            return $msgr;
        }

        $quizId = $quiz->getId();
        $quiz   = $this->fullyHydrateQuiz($quiz);

        // discard all update notifications if the submissionbox isn't open
        $openStamp = $quiz->getStart()->getUnixTimeStamp();
        $closeStamp = $quiz->getFinish()->getUnixTimeStamp();
        $now = time();

        if($openStamp > $now || $closeStamp < $now) {
            return $msgr;
        }

        // if there's no actual difference
        if(!count($diff)) {
            return $msgr;
        }

        $quizName = $quiz->getTitle();
        $quizType = $quiz->getWorkType()->getName();

        $cat     = $quiz->getCategory();
        $catId   = $cat->getId();
        $catName = $cat->getName();

        $icon = "/images/icon.php?size={$this->iconSize}&icon=work";
        $href = "/assess/intro/$quizId";

        // notify all student's parents
        $students = $this->userAccessMapper->findStudentsInCategory($quiz->getCategory());

        // renamed
        if (isset($diff['title'])) {
            $msg = new Message(":child's $quizType was renamed from :oldname to :newname in :catname", $icon, $href);
            foreach ($students as $student) {
                $msgr->targetToParent(
                    $student->getId(),
                    $msg,
                    "assess.$quizId.updated",
                    Logger::NOTICE,
                    array(
                        'child'   => array($student->formatName('{firstname}'), "/search/user/".$student->getId()),
                        'oldname' => $diff['title'][1],
                        'newname' => array($diff['title'][0], "/assess/intro/$quizId"),
                        'catname' => array($catName, "/homepage/$catId"),
                    )
                );
            }
            unset($diff['title']);
        }


        // extended or brought forward
        if (isset($diff['finish'])) {
            // strtotime is safe here because dates are in 'YYYY-MM-DD H:i:s' format
            $oldDate = strtotime($diff['finish'][1]);
            $newDate = strtotime($diff['finish'][0]);

            $verb = $newDate > $oldDate ? 'extended' : 'changed';
            $relative = new Alaress_Date_Format($newDate, 'U');

            $date = $relative->getLongDateOnly();
            if (!$relative->isAtEndOfDay()) {
                $date = $relative->getTimeOnly() .' on '.$date;
            }

            $msg = new Message(":child's $quizType :quizname closing date was $verb to :date in :catname", $icon, $href);
            foreach ($students as $student) {
                $msgr->targetToParent(
                    $student->getId(),
                    $msg,
                    "assess.$quizId.updated",
                    Logger::NOTICE,
                    array(
                        'child'   => array($student->formatName('{firstname}'), "/search/user/".$student->getId()),
                        'quizname' => array($quizName, "/assess/intro/$quizId"),
                        'date'    => $date,
                        'catname' => array($cat->getName(), "/homepage/$catId"),
                    )
                );
            }
            unset($diff['finish']);
        }

        // the student version of this method spawned the mark visibility change, if any
        unset($diff['markVisibility']);

        // if none of the above apply, but there are changes, just be generic
        if (count($diff) && $msgr->isEmpty()) {
            $msg = new Message(":child's $quizType :quizname was updated in :catname", $icon, $href);
            foreach ($students as $student) {
                $msgr->targetToParent(
                    $student->getId(),
                    $msg,
                    "assess.$quizId.updated",
                    Logger::NOTICE,
                    array(
                        'child'    => array($student->formatName(), "/search/user/".$student->getId()),
                        'quizname' => array($quizName, "/assess/intro/$quizId"),
                        'catname'  => array($cat->getName(), "/homepage/$catId"),
                    )
                );
            }
        }

        return $msgr;
    }

	/**
	 * Notify the feed of box closures
	 *
	 * @param Model $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyFeedClosed($quiz, $owner, $diff = null) {
       $envelope = new Envelope();
//		$msgr = new Messenger();

		if($quiz->getFinish()->getUnixTimeStamp() > time()) {
			return $envelope;
		}

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

//		$cat     = $quiz->getCategory();
//		$catId   = $cat->getId();
//		$catName = $cat->getName();

		$icon = "/images/icon.php?size={$this->iconSize}&icon=cross";
		$href = "/intro/assess/$quizId";

        $bob = new Builder();
        $bob->addTopic("assess.$quizId.closed")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->setTitle(":quizname has closed in :catname")
            ->setHref($href)
            ->setIcon($icon)
            ->setParam('quizname', new Param($quizName, $href))
            ->setParam('catname', new CategoryParam($quiz->getCategory()));

        $envelope->broadcastToCategory($quiz->getCategory(), UserAccess::ACCESS_READ, $bob->build());
        return $envelope;

//		$msg = new Message(":quizname has closed in :catname", $icon, $href);
//
//		$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.closed", Logger::NOTICE, array(
//			'quizname' => array($quizName, $href),
//			'catname' => array($catName, "/homepage/$catId"),
//		));
//
//		return $msgr;
	}


}
