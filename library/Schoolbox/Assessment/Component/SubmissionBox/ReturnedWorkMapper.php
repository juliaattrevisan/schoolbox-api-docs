<?php
/**
 * Mapper for the Submission model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;

class ReturnedWorkMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_return');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\ReturnedWork');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'box_id',
			'property'   => 'submissionBox',
			'type'       => self::TYPE_MODEL,
			'join'       => 'submission_box',
		));
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner',
		));
		$this->hasMap(array(
			'column'     => 'comment',
			'property'   => 'comment',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'mark',
			'property'   => 'mark',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'norm_mark',
			'property'   => 'normalizedMark',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'filename',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'submitted',
			'property'   => 'submitted',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'returned',
			'property'   => 'returned',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasJoin('submission_box', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
			'this_key'  => 'box_id',
			'other_key' => 'id',
		));
		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));
	}

	/**
	 * Gets the list of activities related to submission returns.
	 *
	 * An activity is returned for each submission return for this user in the
	 * given categories.
	 *
	 * @param int   $user_id      The user for who activities are fetched for.
	 * @param int   $limit        The max number of activities to be fetched.
	 * @param array $categories   The categories to fetch activities for.
	 * @param array $visibilities Which MARK_VISIBILITY* constants are viewable?
	 *
	 * @return ActivityCollection
	 */
	public function getActivities($user_id, $limit, $categories, $visibilities) {

		//
		// User mapper: I want to know if the user (requesting user, not user
		// whose activities are fetched) is a parent, or a student.
		// Parent:  sb.visibility = ALL
		// Student: sb.visibility IN (ALL, STUDENTS)

		$collection    = new ActivityCollection();
		$factory       = new ActivityFactory();
		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');
		$query = "
			SELECT
				{$this->getColumnString('sr')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')}
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
			AND sb.mark_visibility IN {$this->getArrayPlaceholders($visibilities, 'vis')}
			AND sr.owner = :user_id
			ORDER BY sr.returned DESC
			LIMIT :limit
		";
		$statement = $this->database->prepare($query);
		$params    = $this->getArrayParameters($categories, 'category') + $this->getArrayParameters($visibilities, 'vis');
		$params['limit']   = $limit;
		$params['user_id'] = $user_id;
		foreach ($params as $key => $value) {
			$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
		}
		$statement->execute();
		foreach ($statement as $row) {
			$collection->add(
				$factory->buildFromReturnedWork(
					$this->hydrateForActivityList($row)
				)
			);
		}
		return $collection;
	}

	/**
	 * Builds list of student's marks for all returns in the given category.
	 *
	 * @param int   $category_id
	 * @param int   $student_id
	 * @param array $extra_params Any extra filters:
	 *                            * year
	 *                            * semester
	 *                            * owner - the creator of the submission box
	 *                            * visibility - a list containing the allowed
	 *                              submission box mark visibility values.
	 *
	 * @return array
	 */
	public function getStudentMarks($category_id, $student_id, $extra_params = array()) {
		$submission_box_mapper = $this->getJoinMapper('submission_box');

		//
		// Build filters for query
		$params = array(
			'category' => $category_id,
			'student'  => $student_id,
		);
		$filters = array(
			'sb.category = :category',
			'sr.owner = :student',
			'sb.deleted IS FALSE',
		);
		if (!empty($extra_params['year'])) {
			$params['year'] = $extra_params['year'];
			$filters[]      = 'YEAR(sb.date) = :year';
		}
		if (!empty($extra_params['semester'])) {
			$semester_params       = $submission_box_mapper->getSemesterMonths($extra_params['semester']);
			$params['month_start'] = $semester_params['start'];
			$params['month_end']   = $semester_params['end'];
			$filters[]             = 'MONTH(sb.date) BETWEEN :month_start AND :month_end';
		}
		if (!empty($extra_params['owner'])) {
			$params['owner'] = $extra_params['owner'];
			$filters[]       = 'sb.owner = :owner';
		}
		if (!empty($extra_params['visibility'])) {
			$params    = array_merge($params,  $this->getArrayParameters($extra_params['visibility'], 'vis'));
			$filters[] = 'sb.mark_visibility IN '.$this->getArrayPlaceholders($extra_params['visibility'], 'vis');
		}
		$filters = implode(' AND ', $filters);


		$query = "
			SELECT sb.id AS box_id, sr.norm_mark
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			WHERE $filters
			ORDER BY sr.returned DESC
		";
		$statement = $this->database->prepare($query);
		$statement->execute($params);

		//
		// Ignore all but the most recent mark for each submission box.
		$marks = array();
		foreach ($statement as $row) {
			$box_id = $row['box_id'];
			$mark   = $row['norm_mark'];
			if (!array_key_exists($box_id, $marks)) {
				$marks[$box_id] = $mark;
			}
		}
		return $marks;
	}

	/**
	 * Retrieves activity list for the student's submission history.
	 *
	 * @param $user_id
	 * @param $box_id
	 *
	 * @return ActivityCollection
	 */
	public function getSubmissionHistory($user_id, $box_id) {
		$collection    = new ActivityCollection();
		$factory       = new ActivityFactory();
		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');
		//
		// Have hydrateFromRow() in ActivityFactory
		$query = "
			SELECT
				{$this->getColumnString('sr')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')}
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			AND sr.owner = :user_id
			AND sb.id = :box_id
			ORDER BY sr.returned DESC
		";
		$statement = $this->database->prepare($query);
		$params['user_id'] = $user_id;
		$params['box_id']  = $box_id;
		foreach ($params as $key => $value) {
			$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
		}
		$statement->execute();
		foreach ($statement as $row) {
			$collection->add(
				$factory->buildFromReturnedWork(
					$this->hydrateForActivityList($row)
				)
			);
		}
		return $collection;
	}

	/**
	 * Custom hydration method used by methods that get an activity list.
	 *
	 * This expects the following aliases:
	 * * sr: this return
	 * * sb: submission box
	 * * c:  category
	 *
	 * @param array $row
	 *
	 * @return ReturnedWork
	 */
	protected function hydrateForActivityList($row) {

		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');

		$return   = $this->hydrate($row, 'sr');
		$box      = $box_join->hydrate($row, 'sb');
		$category   = $category_join->hydrate($row, 'c');
		$box->setCategory($category);
		$return->setSubmissionBox($box);
		return $return;
	}
}
