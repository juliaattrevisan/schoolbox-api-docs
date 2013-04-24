<?php
/**
 * Factory for building activity objects out of different assessment objects.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment;

use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

class ActivityFactory {

	/**
	 * @param SubmissionBox $box
	 * @param string        $event The event type: "new", "overdue" or "closed"
	 *
	 * @return null|Activity
	 */
	public function buildFromSubmissionBox(SubmissionBox $box, $event) {
		$date = null;
		if ($event === 'new') {
			$date = $box->getOpenDate();
		}
		elseif ($event === 'overdue') {
			$date = $box->getDueDate();
		}
		elseif ($event === 'closed') {
			$date = $box->getLateDate();
		}
		if (!isset($date)) {
			return null;
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
	 * @param OnlineAssessment $assess
	 * @param string           $event  The event type: "new" or "closed"
	 * @return null|Activity
	 */
	public function buildFromOnlineAssessment(OnlineAssessment $assess, $event) {
		$date = null;
		if ($event === 'new') {
			$date = $assess->getStart();
		}
		elseif ($event === 'closed') {
			$date = $assess->getFinish();
		}
		if (!isset($date)) {
			return null;
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
	 * @param Submission $submission
	 * @return Activity
	 */
	public function buildFromStudentSubmission(Submission $submission) {
		$activity = new Activity();
		$activity
			->setEvent('submitted')
			->setSubmissionBox($submission->getSubmissionBox())
			->setOwner($submission->getOwner())
			->setCategory($submission->getSubmissionBox()->getCategory())
			->setSubmission($submission)
			->setDate($submission->getSubmitted());
		return $activity;
	}

	/**
	 * @param ReturnedWork $return
	 * @return Activity
	 */
	public function buildFromReturnedWork(ReturnedWork $return) {
		$activity = new Activity();
		$activity
			->setEvent('returned')
			->setSubmissionBox($return->getSubmissionBox())
			->setOwner($return->getOwner())
			->setCategory($return->getSubmissionBox()->getCategory())
			->setReturnedWork($return)
			->setDate($return->getReturned());
		return $activity;
	}
}
