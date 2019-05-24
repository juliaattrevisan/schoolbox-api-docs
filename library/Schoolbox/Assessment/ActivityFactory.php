<?php
/**
 * Factory for building activity objects out of different assessment objects.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment;

use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use UnexpectedValueException;

class ActivityFactory {

	/**
	 * @param SubmissionBox $box
	 * @param string        $event The event type
     * @see Activity::EVENT_* constants
	 * @return Activity
     * @throws UnexpectedValueException If unable to find a suitable date for the submission box and event
	 */
    public function buildFromSubmissionBox(SubmissionBox $box, string $event): Activity
    {
		$date = null;
        if ($event === Activity::EVENT_NEW) {
			$date = $box->getOpenDate();
		}
        elseif ($event === Activity::EVENT_OVERDUE || $event === Activity::EVENT_UPCOMING) {
			$date = $box->getDueDate();
		}
        elseif ($event === Activity::EVENT_CLOSED) {
			$date = $box->getLateDate();
		}
		if (!isset($date)) {
            throw new UnexpectedValueException("Unable to find suitable '$event' date for submission box ({$box->getId()})");
		}
		$activity = new Activity();
		$activity
			->setEvent($event)
			->setSubmissionBox($box)
			->setOwner($box->getOwner())
			->setCategory($box->getCategory())
			->setDate($date);
		return $activity;
	}

	/**
	 * @param Quiz $assess
	 * @param string        $event The event type: "new", "closed" or "upcoming"
	 * @return Activity
     * @throws UnexpectedValueException If unable to find a suitable date for the quiz and event
	 */
	public function buildFromOnlineAssessment(Quiz $assess, string $event): Activity
    {
		$date = null;
		if ($event === Activity::EVENT_NEW) {
			$date = $assess->getStart();
		}
        elseif ($event === Activity::EVENT_CLOSED || $event === Activity::EVENT_UPCOMING) {
			$date = $assess->getFinish();
		}
		if (!isset($date)) {
            throw new UnexpectedValueException("Unable to find suitable '$event' date for quiz ({$assess->getId()})");
		}
		$activity = new Activity();
		$activity
			->setEvent($event)
			->setAssessment($assess)
			->setOwner($assess->getUser())
			->setCategory($assess->getCategory())
			->setDate($date);
		return $activity;
	}

	/**
	 * @param Submission\Submission $submission
	 * @return Activity
	 */
	public function buildFromStudentSubmission(Submission\Submission $submission) {
		$activity = new Activity();
		$activity
			->setEvent(Activity::EVENT_SUBMITTED)
			->setSubmissionBox($submission->getSubmissionBox())
			->setOwner($submission->getOwner())
			->setCategory($submission->getSubmissionBox()->getCategory())
			->setSubmission($submission)
			->setDate($submission->getCreatedAt());
		return $activity;
	}

	/**
	 * @param ReturnedWork $return
	 * @return Activity
	 */
	public function buildFromReturnedWork(ReturnedWork $return) {
		$activity = new Activity();
		$activity
			->setEvent(Activity::EVENT_RETURNED)
			->setSubmissionBox($return->getSubmissionBox())
			->setOwner($return->getOwner())
			->setCategory($return->getSubmissionBox()->getCategory())
			->setReturnedWork($return)
			->setDate($return->getCreatedAt());
		return $activity;
	}
}
