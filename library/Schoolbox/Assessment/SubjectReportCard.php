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
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
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

	/**
	 * Only due work items with a mark visibility value in this array will be
	 * retrieved by getGradeAverage().
	 *
	 * If this is empty, it is ignored and all marks will be retrieved.
	 *
	 * @var array
	 */
	protected $visibilities = array();

	public function __construct($categoryId, $userId, $year = 0, $semester = 0, $visibilities = array()) {

		// dependencies
		$catMap = new CategoryMapper();
		$uMap = new UserMapper();

		$this->targetYear = $year;
		$this->targetSemester = $semester;
		$this->visibilities = $visibilities;

		// TODO check (somehow) that user is (or was) a member of this category

		// build thyself from data we've been given
		$this->category = $catMap->findById($categoryId);
		$this->user = $uMap->findById($userId);

		$this->_fullClassHistory = new ClassStatistics($this->getCategory(), $year, $semester, $this->user);
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
							try {
								$this->_teachers[] = $userMapper->findById($teacher['uid']);
							}
							catch (\Schoolbox\Mapper\Exception $e) {}
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

			/* Visibilities
			 */
			$quizVisibility = new \Assess\Visibility();
			$quizVisibilities = $quizVisibility->getVisibilityArray($this->user);

			// retrieve subject grades.
			// Ensure that grades are properly ordered by date.
			$studentGrades = array_merge(
				$returnMapper->getStudentMarks($this->category->getId(), $this->user->getId(), array(
					'year'       => $this->targetYear,
					'semester'   => $this->targetSemester,
					'visibility' => $this->visibilities,
				)),
				$assessMapper->getStudentMarks($this->category->getId(), $this->user->getId(), array(
					'year'     => $this->targetYear,
					'semester' => $this->targetSemester,
					'visibility' => $quizVisibilities,
				))
			);
			$studentGrades = array_filter(
				$studentGrades,
				function($grade) { return isset($grade['mark']); }
			);
			usort($studentGrades, function($a, $b) { return $a['date'] > $b['date'] ? -1 : 1; });

			// Load grade groups
			$gradeGroups = array();
			foreach ($studentGrades as $grade) {
				if ($grade['mark_type'] == MarkType::TYPE_LETTER) {
					$gradeGroups[] = $grade['submission_grade_group_id'];
				}
			}
			if (empty($gradeGroups)) {
				// No grade groups: just show whatever the standard is.
				$gradeGroups[] = \Config::get('default_grade_group');
			}
			$gradeGroups = array_unique($gradeGroups);
			sort($gradeGroups);

			$gradeAvg = $this->calculateAverageFromStudentGrades($studentGrades);
			$trendAmt = null;

			// if more than 1 assessment, can calculate trending
			if (count($studentGrades) > 1) {
				// get previous average by removing most recent and recalculating
				// (most recent is at head of studentGrades, not tail)
				array_shift($studentGrades);
				$prevAvg = $this->calculateAverageFromStudentGrades($studentGrades);
				if($prevAvg != 0) {
					$trendAmt = ($gradeAvg - $prevAvg) / $prevAvg * 100;
				}
			}

			$this->_gradeAverage = array(
				'grade'  => $gradeAvg,
				'trend'  => $trendAmt,
				'groups' => $gradeGroups,
			);
		}

		return $this->_gradeAverage;
	}

	/**
	 * Calculates a grade average for this set of grades. This calculation
	 * respects weighting of each mark.
	 *
	 * @param  array $studentGrades
	 * @return float The grade average, or null if there is no average.
	 */
	protected function calculateAverageFromStudentGrades($studentGrades) {
		$average     = null;
		$totalWeight = 0;
		foreach ($studentGrades as $grade) {
			$totalWeight += $grade['weight'];
		}
		if ($totalWeight) {
			foreach ($studentGrades as $grade) {
				$average += $grade['mark'] * $grade['weight'] / $totalWeight;
			}
		}
		return $average;
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
            // get rubric details
            $criterionCollection = CriterionMapper::getInstance()->findByColumnList(
                                                  array(
                                                      'parent_id'   => $this->category->getId(),
                                                      'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
                                                  ),
                                                      array('order' => array('grouping'=>'ASC', 'name'=>'ASC'))
            );
		}

        /* Create group structure */
        foreach($criterionCollection as $crit) {
            $this->_criteria[$crit->getGrouping()][] = $crit;
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