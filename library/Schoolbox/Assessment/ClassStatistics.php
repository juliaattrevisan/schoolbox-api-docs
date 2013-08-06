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
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
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
	protected $submissionBoxMapper;

	/**
	 * @var SubmissionMapper
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
	 * @param int $year
	 * @param int $semester
	 */
	public function __construct(Category $category, $year = 0, $semester = 0) {
		$this->category = $category;

		// compile the due work, assessments, marks returned, et al
		$this->submissionBoxMapper = new SubmissionBoxMapper();
		$this->submissionMapper = new SubmissionMapper();
		$this->returnMapper = new SubmissionBox\ReturnedWorkMapper();

		$this->assessMapper = new OnlineAssessmentMapper();
		$this->attemptMapper = new AttemptMapper();

		// retrieve ALL the submission boxes for this category
		$boxCollection = $this->submissionBoxMapper->findByColumn('category', $this->getCategory()->getId());
		foreach($boxCollection as $box) {
			/**
			 * @var $box SubmissionBox
			 */
			$due = $box->getDueDate();
			$dueYear = $due->getUsingFormat('Y');
			$dueSemester = $due->getUsingFormat('m') > 6 ? 2 : 1;

			// if year (and possibly also semester) are given, filter dates
			if($year) {
				if($dueYear == $year) {
					if($semester) {
						if($dueSemester == $semester) {
							$this->addWork($box);
						}
					}
					else {
						$this->addWork($box);
					}
				}
			}
			else {
				$this->addWork($box);
			}
		}

		// retrieve ALL the online assessments (not surveys) for this category
		$assessCollection = $this->assessMapper->findByColumnList(array(
			'category_id' => $this->getCategory()->getId(),
			'availability' => 'assess'
		));
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
			/* @var $return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork */
			$userId = $return->getOwner()->getId();
			if (!array_key_exists($userId, $marks)) {
				$this_mark = $return->getNormalizedMark();
				$marks[$userId] = array(
					'mark' => $this_mark,
					'display_mark' => $return->getMark(),
					'comment' => $return->getComment(),
					'attempts' => 0,
				);
				// grab numbers for averages
				// This only counts toward average if there's an actual mark.
				if (isset($this_mark)) {
					$count++;
				}
				$sum += $return->getNormalizedMark();
			}
		}

		// retrieve submissions and append any user IDs that haven't been marked
		$submissions = $this->submissionMapper->findByColumnList(array(
			'box_id' => $box->getId(),
		));

		foreach($submissions as $submission) {
			/* @var $submission Submission */
			$userId = $submission->getOwner()->getId();
			if(!array_key_exists($userId, $marks)) {
				$marks[$userId]['attempts'] = 1;
			}
			else {
				$marks[$userId]['attempts']++;
			}
		}

		// calculate and insert an average
		$marks['avg'] = $count ? $sum / $count : null;

//		echo "<b>MARRRKS</b><br/>\n";
//		var_dump($marks);
//		echo "<br/><br/>\n";

		return $marks;
	}

	public function getHistoryIsolatingUser(User $user) {
		$id = $user->getId();
		$data = array();

		foreach($this->workMap as $stamp => $work) {

			if($work['object'] instanceof SubmissionBox) {
				$title = $work['object']->getName();
				$visibility = $work['object']->getMarkVisibility();
			}
			elseif($work['object'] instanceof OnlineAssessment) {
				$title = $work['object']->getTitle();
				$visibility = 2;
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
				'average_mark' => $avg,
				'visibility' => $visibility
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

			$marks = array_key_exists($id, $work['marks']) ? $work['marks'][$id] : array('attempts' => 0);
			$works[$stamp] = array(
				'object' => $work['object'],
				'marks' => $marks
			);
		}
		ksort($works);
		return array_values($works);
	}
}