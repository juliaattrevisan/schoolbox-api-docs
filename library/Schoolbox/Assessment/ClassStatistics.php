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
	 * @var Submission\FileMapper
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
	 * Constructor
	 *
	 * @param Category $category
	 * @param Term\Model $term
	 * @param User $user
	 */
	public function __construct($category, Term\Model $term, User $user) {
		$this->category = $category;

		// compile the due work, assessments, marks returned, et al
		$this->submissionBoxMapper = new SubmissionBoxMapper();
		$this->submissionMapper = Submission\FileMapper::getInstance();
		$this->returnMapper = new SubmissionBox\ReturnedWorkMapper();

		$this->assessMapper = new OnlineAssessmentMapper();
		$this->attemptMapper = new AttemptMapper();

		/* Visibility
		 * Exclude hidden quizzes from the Assessments cube
		 */
		$quizVisibility = new \Assess\Visibility();
		$quizVisibilities = $quizVisibility->getVisibilityArray($user);

		// retrieve ALL the submission boxes for this category
		$boxCollection = $this->submissionBoxMapper->findByColumnList(
			array(
				'category'        => $this->getCategory()->getId(),
				'task_id'         => null,
				'mark_visibility' => $quizVisibilities
			)
		);

		// I don't think that term can ever be null.
		// If it's found that it can, then I'll have to just add all boxes.
		foreach($boxCollection as $box) {
			if ($term->contains($box->getDueDate())) {
				$this->addWork($box);
			}
		}

		// retrieve ALL the online assessments (not surveys) for this category
		$assessCollection = $this->assessMapper->findByColumnList(array(
			'category_id'     => $this->getCategory()->getId(),
			'availability'    => 'assess',
			'mark_visibility' => $quizVisibilities
		));
		foreach($assessCollection as $assess) {
			if ($term->contains($assess->getFinish())) {
				$this->addWork($assess);
			}
		}
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

		// Later, this should be updated to return in most recent order.
		// $this->returnMapper->findByColumn('box_id', $box->getId(), array('returned' => 'desc');
		$returnCollection = $this->returnMapper->findByColumn('box_id', $box->getId());

		// iterate across returns and index by student id
		foreach ($returnCollection as $return) {
			/* @var $return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork */
			$userId = $return->getOwner()->getId();

			// Don't use this mark if it's not more recent than an existing mark
			// for this student.
			if (array_key_exists($userId, $marks)) {
				$thisReturnDate = $return->getReturned();
				$lastReturnDate = $marks[$userId]['date'];
				if ($thisReturnDate->compare($lastReturnDate) < 0) {
					continue;
				}
			}

			$marks[$userId] = array(
				'mark'         => $return->getNormalizedMark(),
				'display_mark' => $return->getMark(),
				'comment'      => $return->getComment(),
				'attempts'     => 0,
				'date'         => $return->getReturned(),
			);
		}

		// Calculate sum and count only after the returned marks are collated.
		foreach ($marks as $mark) {
			if (isset($mark['mark'])) {
				$count++;
				$sum += $mark['mark'];
			}
		}

		// retrieve submissions and append any user IDs that haven't been marked
		$submissions = $this->submissionMapper->findByColumnList(array(
			'box_id' => $box->getId(),
		));

		foreach ($submissions as $submission) {
			$userId = $submission->getOwner()->getId();
			if (!array_key_exists($userId, $marks)) {
				$marks[$userId]['attempts'] = 1;
			} else {
				$marks[$userId]['attempts']++;
			}
		}

		// calculate and insert an average
		$marks['avg'] = $count ? $sum / $count : null;

		return $marks;
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

	/**
	 * Get all the students data for box and whisker averages
	 * @return array
	 */
	public function getAllUsersMarks() {
		$allData = array();
		foreach ($this->workMap as $dueDate => $current) {
			$box = $current['object'];
			$allMarks = $current['marks'];
			$allData[$dueDate] = array();
			/* Stack results
             */
			foreach ($allMarks as $userId => $currentMark) {
				if ($box instanceof OnlineAssessment) {
					if ($userId !== 'avg') {
						if (count($currentMark)) {
							$latestAttempt = array_pop($currentMark);
							$allData[$dueDate][] = array(
								'info' => null,
								'uid' => null,
								'value' => (float)$latestAttempt['mark']
							);
						}
					}
				}
				elseif ($box instanceof SubmissionBox) {
					if (strlen(trim($currentMark['mark']))) {
						$allData[$dueDate][] = array(
							'info' => null,
							'uid' => null,
							'value' => (float)$currentMark['mark']
						);
					}
				}
			}
		}
		ksort($allData);
		return array_values($allData);
	}

}