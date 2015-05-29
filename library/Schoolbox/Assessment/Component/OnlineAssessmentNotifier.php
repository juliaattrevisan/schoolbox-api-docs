<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component;


use Alaress\Async\Notification\Instance\Instance;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;

class OnlineAssessmentNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_CLOSED    = 'closed';
	const TRIGGER_OPEN      = 'open';
	const TRIGGER_OVERDUE   = 'overdue';
	const TRIGGER_UPCOMING  = 'upcoming';
	const TRIGGER_UPDATED   = 'updated';

	// this should be configurable, but this will do for now
	const UPCOMING_WINDOW     = 2;

	/** @var CategoryMapper */
	protected $categoryMapper;

	/** @var AttemptMapper */
	protected $attemptMapper;

	/** @var WorkTypeMapper */
	protected $workTypeMapper;

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
		$this->categoryMapper = CategoryMapper::getInstance();
		$this->attemptMapper = AttemptMapper::getInstance();
		$this->workTypeMapper = WorkTypeMapper::getInstance();
		parent::loadDependencies($dependencies);
	}


	/**
	 * Fully hydrates the quiz object, in this case with the work type and
	 * category.
	 *
	 * @todo Remove this when mapper findBy methods can specify joins to hydrate
	 *
	 * @param  OnlineAssessment $quiz
	 * @return OnlineAssessment
	 */
	protected function fullyHydrateQuiz(OnlineAssessment $quiz) {
		// @mattday I've got a surprise for you - it's poop!
		/** @var OnlineAssessment $quiz */
		$quiz = $this->mapper->findById($quiz->getId());
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
		/** @var OnlineAssessment $quiz */
		$quiz = $bundle->getEvent()->getObject();

        // the mere fact that this is required is ludicrous
        if (!($quiz->getStart() && $quiz->getFinish())) {
            $quiz = $this->fullyHydrateQuiz($quiz);
        }

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

		/** @var OnlineAssessment[]|Collection $quizzes */
		$quizzes = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($quizzes as $quiz) {
			$c->merge(new Collection($this->notifyStudentOpen($quiz, $quiz->getUser())->getAll($quiz->getStart()->getUnixTimestamp())));
			$c->merge(new Collection($this->notifyFeedClosed($quiz, $quiz->getUser())->getAll($quiz->getFinish()->getUnixTimestamp())));
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

		$quiz = $this->fullyHydrateQuiz($quiz);
		$totalUnattempted = $this->getStudentsUnattemptedList($quiz)->count();

		// everyone submitted! woohoo!
		if($totalUnattempted == 0) {
			return $msgr;
		}

		$quizId   = $quiz->getId();
		$quizName = $quiz->getTitle();

		$icon = '/images/icon.php?size=32&icon=work';
		$href = "/assess/results/$quizId";

		$msg = new Message(":quizname has closed and :number did not submit work", $icon, $href);

		$msgr->targetToUser($owner->getId(), $msg, "assess.$quizId.closed", Logger::ERROR, array(
			'boxname' => array($quizName, "/assess/results/$quizId"),
			'number'  => sprintf("%s student%s", $totalUnattempted, $totalUnattempted == 1 ? '' : 's')
		));

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

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizType = $quiz->getWorkType()->getName();
		$quizName = $quiz->getTitle();

		$ownerId    = $owner->getId();
		$ownerName  = $owner->formatName();
		$ownerExtId = $owner->getExternalId();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$indefArticle = in_array(strtolower($quizType[0]), array('a','e','i','o','u')) ? 'an' : 'a';


		$msg = new Message(
			":username opened $indefArticle $quizType, :quizname, in :catname",
			"/portrait?userId=$ownerExtId&size=square32", "/assess/intro/$quizId"
		);

		$params = array(
			'username' => array($ownerName, "/search/user/$ownerId"),
			'quizname' => array($quizName, "/assess/intro/$quizId"),
			'catname'  => array($catName, "/homepage/$catId")
		);

		$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.open", Logger::NOTICE, $params,
			array('roletype' => Role::STUDENT)
		);

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

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$icon = '/images/icon.php?size=32&icon=work';
		$href = "/assess/intro/$quizId";

		$msg = new Message(":boxname has closed in :catname", $icon, $href);

		$unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

		foreach($unattemptedStudents as $user) {
			$msgr->targetToUser($user->getId(), $msg, "assess.$quizId.overdue", Logger::ERROR, array(
				'quizname' => array($quizName, $href),
				'catname'  => array($catName, "/homepage/$catId"),
			));
		}

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

		if($this->conf->get('restrict_parent_duework_access')) {
			return $msgr;
		}

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$icon = '/images/icon.php?size=32&icon=work';
		$href = "/assess/intro/$quizId";

		$msg = new Message(":child did not attempt :quizname in :catname", $icon, $href);

		$unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

		foreach($unattemptedStudents as $student) {

			$childName = $student->getFirstname();
			$childId = $student->getId();

			$msgr->targetToUser($childId, $msg, "assess.$quizId.overdue", Logger::ERROR, array(
				'child'    => array($childName, "/search/user/$childId"),
				'quizname' => array($quizName, $href),
				'catname'  => array($catName, "/homepage/$catId"),
			));
		}


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

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$icon = '/images/icon.php?size=32&icon=work';
		$href = "/assess/intro/$quizId";

		$when = self::UPCOMING_WINDOW . " hour" . self::UPCOMING_WINDOW == 1 ? '' : 's';
		$msg = new Message(":quizname is due in $when in :catname", $icon, $href);

		$unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

		foreach($unattemptedStudents->getColumnValues('id') as $id) {
			$msgr->targetToUser($id, $msg, "assess.$quizId.upcoming", Logger::WARNING, array(
				'quizname' => array($quizName, $href),
				'catname' => array($catName, "/homepage/$catId"),
			));
		}

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

		if($this->conf->get('restrict_parent_duework_access')) {
			return $msgr;
		}

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$href = "/assess/intro/$quizId";

		$unattemptedStudents = $this->getStudentsUnattemptedList($quiz);

		foreach($unattemptedStudents as $student) {
			$childName = $student->getFirstname();
			$childId = $student->getId();
			$childExtId = $student->getExternalId();

			$icon = "/portrait?userId=$childExtId&size=square32";

			$msg = new Message(":child has not yet attempted :quizname in :catname", $icon, $href);

			// don't provide a link for parents to click on if it's just going to give the a 403
			$quizdata = $this->conf->get('restrict_student_submissions')
				? $quizName
				: array($quizName, "/submission/submissionHistory/$quizId");

			$msgr->targetToParent($childId, $msg, "assess.$quizId.upcoming", Logger::WARNING, array(
				'child'     => array($childName, "/search/user/$childId"),
				'quizname'  => $quizdata,
				'catname'   => array($catName, "/homepage/$catId")
			));
		}

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

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		// discard all update notifications if the submissionbox isn't open
		$openStamp = $quiz->getStart()->getUnixTimestamp();
		$closeStamp = $quiz->getFinish()->getUnixTimestamp();
		$now = time();

		if($openStamp > $now || $closeStamp < $now) {
			return $msgr;
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

		$icon = '/images/icon.php?size=32&icon=work';
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


            $dateformat = $relative->getLongDateOnly();
            if(!$relative->isAtEndOfDay()) {
                $dateformat = $relative->getTimeOnly() .' on '.$dateformat;
            }

			$msg = new Message(":author $verb :boxname to :date in :catname", $icon, $href);

			$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.datechanged", Logger::NOTICE, array(
					'author' => array($ownerName, "/search/user/$ownerId"),
					'boxname' => array($quizName, "/assess/intro/$quizId"),
					'date' => date($dateformat, $relative->getUnixTimestamp()),
					'catname' => array($cat->getName(), "/homepage/$catId")
				),
				array(
					'roletype' => Role::STUDENT
				)
			);

			unset($diff['finish']);
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
     * @param OnlineAssessment $quiz
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
        $openStamp = $quiz->getStart()->getUnixTimestamp();
        $closeStamp = $quiz->getFinish()->getUnixTimestamp();
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

        $icon = '/images/icon.php?size=32&icon=work';
        $href = "/assess/intro/$quizId";

        // notify all student's parents
        $students = UserAccessMapper::getInstance()->findStudentsInCategory($quiz->getCategory());

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
                        'child'   => array($student->formatName(), "/search/user/".$student->getId()),
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

            $dateformat = $relative->getLongDateOnly();
            if (!$relative->isAtEndOfDay()) {
                $dateformat = $relative->getTimeOnly() .' on '.$dateformat;
            }

            $msg = new Message(":child's $quizType :boxname closing date was $verb to :date in :catname", $icon, $href);
            foreach ($students as $student) {
                $msgr->targetToParent(
                    $student->getId(),
                    $msg,
                    "assess.$quizId.updated",
                    Logger::NOTICE,
                    array(
                        'child'   => array($student->formatName(), "/search/user/".$student->getId()),
                        'boxname' => array($quizName, "/assess/intro/$quizId"),
                        'date'    => date($dateformat, $relative->getUnixTimestamp()),
                        'catname' => array($cat->getName(), "/homepage/$catId"),
                    )
                );
            }
            unset($diff['finish']);
        }

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
	 * @param OnlineAssessment $quiz
	 * @param User $owner
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyFeedClosed($quiz, $owner, $diff = null) {
		$msgr = new Messenger();

		if($quiz->getFinish()->getUnixTimestamp() > time()) {
			return $msgr;
		}

		$quizId = $quiz->getId();
		$quiz   = $this->fullyHydrateQuiz($quiz);

		$quizName = $quiz->getTitle();

		$cat     = $quiz->getCategory();
		$catId   = $cat->getId();
		$catName = $cat->getName();

		$icon = '/images/icon.php?size=32&icon=cross';
		$href = "/intro/assess/$quizId";

		$msg = new Message("Submissions have closed for :boxname in :catname", $icon, $href);

		$msgr->broadcastToCategory($catId, $msg, "assess.$quizId.closed", Logger::NOTICE, array(
			'boxname' => array($quizName, $href),
			'catname' => array($catName, "/homepage/$catId"),
		));

		return $msgr;
	}


}