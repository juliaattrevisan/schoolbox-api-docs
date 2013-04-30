<?php
/**
 * Mapper for online assessment attempts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use \Schoolbox\Mapper;

class AttemptMapper extends Mapper {
	protected function mapping() {
		$this->hasTable('assess_attempt');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\OnlineAssessment\\Attempt');
		$this->hasMap(array(
			'column'     => 'attempt_id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'assess_id',
			'property'   => 'assessment',
			'type'       => self::TYPE_MODEL,
			'join'       => 'assessment',
		));
		$this->hasMap(array(
			'column'     => 'user_id',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'join'       => 'user',
		));
		$this->hasMap(array(
			'column'     => 'start',
			'property'   => 'start',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'finish',
			'property'   => 'finish',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'reviewed_at',
			'property'   => 'reviewed',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasSoftDeleteDateField('deleted_at');
	}


	/**
	 * Builds list of student's marks for all attempts in the given category.
	 *
	 * @param int   $category_id
	 * @param int   $student_id
	 * @param array $extra_params Any extra filters:
	 *                            * year
	 *                            * semester
	 *                            * owner - the creator of the submission box
	 *
	 * @return array
	 */
	public function getStudentMarks($category_id, $student_id, $extra_params = array()) {
		//
		// Move getSemesterMonths() to some sort of helper method
		$submission_box_mapper = new SubmissionBoxMapper();

		//
		// Build filters for query
		$params = array(
			'category' => $category_id,
			'student'  => $student_id,
		);
		$filters = array(
			'a.category_id = :category',
			'aa.user_id = :student',
			'aa.deleted_at IS NULL',
			'aq.deleted_at IS NULL',
		);
		if (!empty($extra_params['year'])) {
			$params['year'] = $extra_params['year'];
			$filters[]      = 'YEAR(a.start) = :year';
		}
		if (!empty($extra_params['semester'])) {
			$semester_params       = $submission_box_mapper->getSemesterMonths($extra_params['semester']);
			$params['month_start'] = $semester_params['start'];
			$params['month_end']   = $semester_params['end'];
			$filters[]             = 'MONTH(a.start) BETWEEN :month_start AND :month_end';
		}
		if (!empty($extra_params['owner'])) {
			$params['owner'] = $extra_params['owner'];
			$filters[]       = 'a.user_id = :owner';
		}
		$filters = implode(' AND ', $filters);

		$query = "
			SELECT
				aa.attempt_id,
				aa.assess_id,
				SUM(ar.mark)  AS mark,
				SUM(aq.marks) AS max_mark
			FROM assess_attempt aa
				LEFT JOIN assess_response ar ON aa.attempt_id  = ar.attempt_id
				LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
				LEFT JOIN assess          a  ON aa.assess_id   = a.assess_id
			WHERE $filters
			GROUP BY aa.assess_id, aa.attempt_id
			ORDER BY aa.start DESC
		";
		$statement = $this->database->prepare($query);
		$statement->execute($params);

		$attempts = array();
		foreach ($statement as $row) {
			if (!array_key_exists($row['assess_id'], $attempts) && $row['max_mark'] != 0) {
				$attempts[$row['assess_id']] = array(
					'mark' => ($row['mark'] / $row['max_mark']) * 100,
					'date' => $row['start'],
				);
			}
		}
		return $attempts;
	}

	public function getAllAttempts($assess_id, $extra_params = array()) {
		//
		// Build filters for query
		$params = array(
			'assess_id' => $assess_id,
		);

		$query = "
			SELECT
				aa.user_id,
				aa.attempt_id,
				SUM(ar.mark)  AS mark,
				SUM(aq.marks) AS max_mark
			FROM assess_attempt aa
				LEFT JOIN assess_response ar ON aa.attempt_id  = ar.attempt_id
				LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
				RIGHT JOIN assess          a  ON aa.assess_id   = a.assess_id
			WHERE a.assess_id = :assess_id
			AND a.availability = 'assess'
			GROUP BY aa.assess_id, aa.attempt_id
			ORDER BY aa.start DESC
		";
		$stmt = $this->database->prepare($query);
		$stmt->execute($params);

		$attempts = array();

		$sum = 0;
		$count = 0;

		foreach($stmt as $row) {
			// only include the most recent attempt for each user (ie. first seen)
			if(!empty($extra_params['most_recent']) && $extra_params['most_recent'] == true) {
				// if the user is already in the array (ie already seen), just increment the attempt count
				if(array_key_exists($row['user_id'], $attempts) || $row['max_mark'] == 0) {
					$attempts[$row['user_id']][0]['attempts']++;
					continue;
				}
			}

			// include
			$mark = ($row['mark'] / $row['max_mark']) * 100;
			$attempts[$row['user_id']][] = array(
				'mark' => $mark,
				'display_mark' => sprintf("%d / %d", $row['mark'], $row['max_mark']),
				'comment' => '',
				'attempts' => 1
			);

			// add to averages vars
			++$count;
			$sum += $mark;
		}
		if(!empty($extra_params['calc_average']) && $extra_params['calc_average'] == true) {
			$attempts['avg'] = $count ? $sum / $count : 0;
		}

		return $attempts;
	}
}