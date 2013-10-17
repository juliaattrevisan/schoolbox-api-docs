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

		$weightArray = array();

		// retrieve the objects based on those seen...if any were seen at all
		if(count($assessmentsSeen['sb'])) {
			$sbCollection = SubmissionBoxMapper::getInstance()->findByColumn('id', array_keys($assessmentsSeen['sb']));
			$weightArray['sb'] = array_combine($sbCollection->getColumnValues('id'), $sbCollection->getColumnValues('weight'));
		}
		else {
			$sbCollection = new Collection();
		}

		if(count($assessmentsSeen['oa'])) {
			$oaCollection = OnlineAssessmentMapper::getInstance()->findByColumn('assess_id', array_keys($assessmentsSeen['oa']));
			$weightArray['oa'] = array_combine($oaCollection->getColumnValues('id'), $oaCollection->getColumnValues('weight'));
		}
		else {
			$oaCollection = new Collection();
		}

		$assessments = new Collection();
		$assessments->merge($sbCollection)->merge($oaCollection);

		// flatten the weight array
		$assessmentWeights = array();
		foreach($weightArray as $type => $ws) {
			foreach($ws as $id => $w) {
				$assessmentWeights["$type-$id"] = $w;
			}
		}
		// get rid of this, we don't need it anymore
		unset($weightArray);

		// sort all the things by their respective due-date fields
		$assessments->sortByCallback(function($a, $b) {
			$x = $a instanceof SubmissionBox ? $a->getDueDate()->getUnixTimestamp() : $a->getFinish()->getUnixTimestamp();
			$y = $b instanceof SubmissionBox ? $b->getDueDate()->getUnixTimestamp() : $b->getFinish()->getUnixTimestamp();
			return $x == $y ? 0 : ($x > $y ? 1 : -1);
		});


		/* Every student's grade average is uniquely calculated, based on the assessable items for which they have
		 * received feedback. If they have not received a mark, or received a 'Not assessed', the mark should not
		 * bear any impact on their average.
		 *
		 * It also has to comprehend grade error codes: 'Absent' == 0, 'Not Assessed' == NULL and 'Not Submitted' == 0
		 */

		$grades = array();

		// this is always just 2 elements
		foreach ($marks as $type => $student) {

			// iterate students with the current OA/SB context
			foreach($student as $userId => $marks) {

				// reset applicable weight for this student
				$applicableWeight = 0;

				// iterate the marks given to the current student
				foreach($marks as $aId => $markArray) {

					// add the current weight to the applicable weight
					$applicableWeight += $assessmentWeights["$type-$aId"];

					// apply and append
					$grades[$userId]['grades']["$type-$aId"] = $markArray['mark'];
				}

				// some students have no grades yet
				if(is_array($grades[$userId]['grades'])) {
					// apply the applicable weight sum to the marks retrieved
					foreach($grades[$userId]['grades'] as $k => $v) {
						$grades[$userId]['grades'][$k] = $v * ($assessmentWeights[$k] / $applicableWeight);
					}
				}
			}
		}

		// summate the weighted grades given
		foreach($grades as $userId => $marks) {
			$grades[$userId]['avg'] = array_sum($marks['grades']);
		}

		return $grades;
	}

}