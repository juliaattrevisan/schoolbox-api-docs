<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class SubjectReportCard {

	const VISIBLE_TO_ALL     = 'all';
	const VISIBLE_TO_STUDENT = 'student';
	const VISIBLE_TO_PARENT  = 'parent';

	const SORT_BY_DATE = 'date';
	const SORT_BY_TITLE = 'title';
	const SORT_BY_COMMENTS = 'comments';

	// domain objects
	/**
	 * @var Category
	 */
	protected $category;

	/**
	 * @var User
	 */
	protected $user;

	// 'andler caching: as handlers are deprecated, the logic around these should be objectified
	protected $_teachers;
	protected $_gradeAverage;
	protected $_userHistory;
	protected $_fullClassHistory;
	protected $_criteria;
	protected $_assessments;
	protected $_comments;

	protected $targetYear;
	protected $targetSemester;


	public function __construct($categoryId, $userId, $year = 0, $semester = 0) {

		// dependencies
		$catMap = new CategoryMapper();
		$uMap = new UserMapper();

		$this->targetYear = $year;
		$this->targetSemester = $semester;

		// TODO check (somehow) that user is (or was) a member of this category

		// build thyself from data we've been given
		$this->category = $catMap->findById($categoryId);
		$this->user = $uMap->findById($userId);

		$this->_fullClassHistory = new ClassStatistics($this->getCategory(), $year, $semester);
	}


	/* REGULAR GETTERS */

	public function getCategory() {
		return $this->category;
	}

	public function getUser() {
		return $this->user;
	}


	/* FORMATTED GETTERS */

	public function getTeachers() {
		if(!is_array($this->_teachers)) {

			// populate cache
			if (\Config::get('external_enable')) {
				$this->_teachers = array();

				$externalClassHandler = new \External_Class();
				$userHandler = new \User();
				$userMapper = new UserMapper();
				$groupHandler = new \Group();

				if($this->category->getCodes()->count() == 0) {
					return array();
				}

				$listSyns = $externalClassHandler->getTeacherName($this->category->getCodes()->first()->getCode());
				$staffGroups = $groupHandler->getStaffGroupTypeIds();

				if (is_array($listSyns)) {
					foreach($listSyns as $synid) {
						/* TODO this is horrible, we need to move this into the usermapper */
						$teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
						if($teacher['uid']) {
							$teacherObj = $userMapper->findById($teacher['uid']);
							if($teacherObj != false) {
								$this->_teachers[] = $teacherObj;
							}
						}
					}
				}
			}
			else {
				return array();
			}
		}
		return $this->_teachers;
	}

	public function getGradeAverage() {
		if(!is_array($this->_gradeAverage)) {
			// populate cache
			$returnMapper = new ReturnedWorkMapper();
			$assessMapper = new AttemptMapper();

			// retrieve subject grades.
			// Ensure that grades are properly ordered by date.
			$studentGrades = array_merge(
//				$returnMapper->getStudentMarks($this->category->getId(), $this->user->getId(), array('visibility' => $visibilities)),
				$returnMapper->getStudentMarks($this->category->getId(), $this->user->getId(), array(
					'year' => $this->targetYear,
					'semester' => $this->targetSemester
				)),
				$assessMapper->getStudentMarks($this->category->getId(), $this->user->getId(), array(
					'year' => $this->targetYear,
					'semester' => $this->targetSemester
				))
			);

			usort($studentGrades, function($a, $b) { return $a['date'] > $b['date'] ? -1 : 1; });
			$studentGrades = array_map(function($row) { return $row['mark']; }, $studentGrades);

			$gradeAvg    = null;
			$trendAmt    = null;
			$grade_count = count($studentGrades);

			// only calculate an average if there are any grades to use
			if ($grade_count > 0) {
				$gradeAvg = array_sum($studentGrades) / count($studentGrades);
			}

			// if more than 1 assessment, can calculate trending
			if ($grade_count > 1) {
				// get previous average by removing most recent and recalculating
				array_pop($studentGrades);
				$prevAvg = array_sum($studentGrades) / count($studentGrades);
				if($prevAvg != 0) {
					$trendAmt = ($gradeAvg - $prevAvg) / $prevAvg * 100;
				}
			}

			$this->_gradeAverage = array(
				'grade' => $gradeAvg,
				'trend' => $trendAmt
			);
		}

		return $this->_gradeAverage;
	}

	public function getUserHistory() {
		if(!is_array($this->_userHistory)) {
			// populate cache


		}
		return $this->_userHistory;
	}

	/**
	 * @return array
	 */
	public function getFullClassHistory() {
		return $this->_fullClassHistory->getHistoryIsolatingUser($this->getUser());
	}

	public function getCriteria() {
		if(!is_array($this->_criteria)) {
			// populate cache
			$rubricHandler = new \DueWork_Rubric_Handler();
			$this->_criteria = $rubricHandler->getAvailableCriteria($this->category->getId());
		}
		return $this->_criteria;
	}

	public function getAssessments($sortBy = self::SORT_BY_DATE) {
		return $this->_fullClassHistory->extractUserMarks($this->getUser());
	}

	public function getComments($visibleTo = self::VISIBLE_TO_ALL) {
		if(!is_array($this->_comments)) {
			// populate cache
		}

		switch($visibleTo) {
			case self::VISIBLE_TO_ALL:
			case self::VISIBLE_TO_PARENT:
			case self::VISIBLE_TO_STUDENT:
			default:
				break;
		}


	}
}