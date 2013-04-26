<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;

use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Resource\Category;
use Schoolbox\User;

class ClassStatistics {

	/**
	 * @var \Schoolbox\Resource\Category
	 */
	protected $category;

	/**
	 * @var array
	 */
	protected $workMap = array();

	/**
	 * @var SubmissionBoxMapper
	 */
	protected $submissionMapper;

	/**
	 * @var ReturnedWorkMapper
	 */
	protected $returnMapper;

	/**
	 * @var OnlineAssessmentMapper
	 */
	protected $assessMapper;

	/**
	 * @var AttemptMapper
	 */
	protected $attemptMapper;

	/**
	 * @param Category $category
	 */
	public function __construct(Category $category) {
		$this->category = $category;

		// compile the due work, assessments, marks returned, et al
		$this->submissionMapper = new SubmissionBoxMapper();
		$this->returnMapper = new SubmissionBox\ReturnedWorkMapper();

		$this->assessMapper = new OnlineAssessmentMapper();
		$this->attemptMapper = new AttemptMapper();

		// map by due timestamp (manage collisions)
		$boxCollection = $this->submissionMapper->findByColumn('category', $this->getCategory()->getId());
		foreach($boxCollection as $box) {
			$this->addWork($box);
		}

		$assessCollection = $this->assessMapper->findByColumn('category_id', $this->getCategory()->getId());
		foreach($assessCollection as $assess) {
			$this->addWork($assess);
		}

//		print_r($this->workMap);

	}

	/**
	 * Return the category this statistics object is referencing
	 *
	 * @return Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * Add a dueWork or onlineAssess to the map, accounting for timestamp collisions
	 *
	 * @param SubmissionBox|OnlineAssessment $work
	 * @return bool
	 */
	protected function addWork($work) {
		if($work instanceof OnlineAssessment) {
			$stamp = $work->getFinish()->getUnixTimestamp();
			$marks = $this->attemptMapper->getAllAttempts($work->getId(), array(
				'most_recent' => true,
				'calc_average' => true
			));
		}
		elseif($work instanceof SubmissionBox) {
			$stamp = $work->getDueDate()->getUnixTimestamp();
			$marks = $this->getDueWorkMarks($work);
		}
		else {
			return false;
		}

		// accommodate map collisions by incrementing seconds
		while(array_key_exists($stamp, $this->workMap)) {
			++$stamp;
		}

		$this->workMap[$stamp] = array(
			'object' => $work,
			'marks' => $marks,
		);
		return true;
	}

	protected function getDueWorkMarks(SubmissionBox $box) {
		$marks = array();
		$count = 0;
		$sum = 0;

		$returnCollection = $this->returnMapper->findByColumn('box_id', $box->getId());

		// iterate across returns and index by student id
		foreach($returnCollection as $return) {
			$userId = $return->getOwner()->getId();
			if(!array_key_exists($userId, $marks)) {
				$marks[$userId] = array(
					'mark' => $return->getNormalizedMark(),
					'comment' => $return->getComment()
				);
				// grab numbers for averages
				++$count;
				$sum += $return->getNormalizedMark();
			}
		}
		// calculate and insert an average
		$marks['avg'] = $count ? $sum / $count : 0;
		return $marks;
	}

	public function getHistoryIsolatingUser(User $user) {
		$id = $user->getId();
		$data = array();

		foreach($this->workMap as $stamp => $work) {

			if($work['object'] instanceof SubmissionBox) {
				$title = $work['object']->getName();
			}
			elseif($work['object'] instanceof OnlineAssessment) {
				$title = $work['object']->getTitle();
			}
			else {
				continue;
			}

			// extract user mark if it exists
			if(array_key_exists($id, $work['marks'])) {
				$mark = $work['marks'][$id];
			}
			else {
				$mark = null;
			}

			// extract average mark if it exists
			if(array_key_exists('avg', $work['marks'])) {
				$avg = $work['marks']['avg'];
			}
			else {
				$avg = null;
			}

			$data[$stamp] = array(
				'timestamp' => $stamp * 1000, // millis, for jsgraph
				'title' => $title,
				'user_mark' => $mark['mark'],
				'average_mark' => $avg
			);
		}

		return $data;
	}

	/**
	 * Extracts the nominated user's marks for the category
	 *
	 * @param User $user
	 * @return array
	 */
	public function extractUserMarks(User $user) {
		$id = $user->getId();
		$works = array();

		foreach($this->workMap as $stamp => $work) {
			$marks = array_key_exists($id, $work['marks']) ? $work['marks'][$id] : false;
			$works[$stamp] = array(
				'object' => $work['object'],
				'marks' => $marks
			);
		}
		ksort($works);
		return array_values($works);
	}
}