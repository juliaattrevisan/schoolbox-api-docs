<?php
/**
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment;


use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Category;
use Schoolbox\User;

class Grades {

	protected static $instance;

	/**
	 * Retrieves the singleton instance of this class.
	 *
	 * @return static
	 */
	static public function getInstance() {
		$class_name = get_called_class();
		if (!isset(self::$instance[$class_name])) {
			self::$instance[$class_name] = new static();
		}
		return self::$instance[$class_name];
	}

	/**
	 * Accepts an array of users, and returns their grades for the given subject.
	 * Mapper-based replacement of SubmissionReturn::getStudentMarksForCategory()
	 *
	 * @param Collection<User> $students
	 * @param Category $subject
	 * @param int $year
	 * @param int $semester
	 * @return array
	 */
	public function getStudentGradesForSubject($students, $subject, $year = 0, $semester = 0) {

		$params = array();

		if($year && $semester) {
			$params['year'] = $year;
			$params['semester'] = $semester;
		}

		$assessmentsSeen = array(
			'sb' => array(),
			'oa' => array()
		);
		$marks = array();

		foreach($students as $user) {
			/** @var User $user */

			// get all OA marks for this student in this category
			$marks['oa'][$user->getId()] = AttemptMapper::getInstance()->getStudentMarks(
				$subject->getId(),
				$user->getId(),
				$params
			);

			foreach($marks['oa'][$user->getId()] as $oa => $m) {
				$assessmentsSeen['oa'][$oa] = true;
			}

			// get all SB marks for this student in this category
			$marks['sb'][$user->getId()] = ReturnedWorkMapper::getInstance()->getStudentMarks(
				$subject->getId(),
				$user->getId(),
				$params
			);

			foreach($marks['sb'][$user->getId()] as $sb => $m) {
				$assessmentsSeen['sb'][$sb] = true;
			}

		}

		// retrieve the objects based on those seen...if any were seen at all
		if(count($assessmentsSeen['sb'])) {
			$sbCollection = SubmissionBoxMapper::getInstance()->findByColumn('id', array_keys($assessmentsSeen['sb']));
		}
		else {
			$sbCollection = new Collection();
		}

		if(count($assessmentsSeen['oa'])) {
			$oaCollection = OnlineAssessmentMapper::getInstance()->findByColumn('assess_id', array_keys($assessmentsSeen['oa']));
		}
		else {
			$oaCollection = new Collection();
		}

		$assessments = new Collection();
		$assessments->merge($sbCollection)->merge($oaCollection);

		// sort all the things by their respective due-date fields
		$assessments->sortByCallback(function($a, $b) {
			$x = $a instanceof SubmissionBox ? $a->getDueDate()->getUnixTimestamp() : $a->getFinish()->getUnixTimestamp();
			$y = $b instanceof SubmissionBox ? $b->getDueDate()->getUnixTimestamp() : $b->getFinish()->getUnixTimestamp();
			return $x == $y ? 0 : ($x > $y ? 1 : -1);
		});

		// calculate the total weight of this set of assessments
		$weightSum = 0;

		foreach($assessments as $a) {
			$weightSum += $a->getWeight();
		}

		$grades = array();

		foreach ($marks as $type => $student) {

			$coll = $type == 'sb' ? $sbCollection : $oaCollection;

			if($coll->count()) {
				$weights = array_combine($coll->getColumnValues('id'), $coll->getColumnValues('weight'));
			}
			else {
				$weights = array();
			}

			foreach($student as $userId => $marks) {

				$gradeList = array();

				foreach($marks as $aId => $markArray) {

					// calculate assessment relative weight index
					$rwi = $weights[$aId] / $weightSum;

					// apply and append
					$gradeList[] = $markArray['mark'] * $rwi;

				}

				$grades[$userId] = array(
					'grades' => $gradeList,
					'avg' => array_sum($gradeList),
				);

			}

		}

		return $grades;

	}


}