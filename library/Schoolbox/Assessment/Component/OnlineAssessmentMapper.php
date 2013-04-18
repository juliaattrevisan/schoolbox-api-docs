<?php
/**
 * Mapper for the OnlineAssessment model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;

class OnlineAssessmentMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('assess');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\OnlineAssessment');
		$this->hasMap(array(
			'column'     => 'assess_id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'category_id',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category',
		));
		$this->hasMap(array(
			'column'     => 'user_id',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'join'       => 'user',
		));
		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'workType',
			'type'       => self::TYPE_MODEL,
			'join'       => 'work_type',
		));
		$this->hasMap(array(
			'column'     => 'title',
			'property'   => 'title',
			'type'       => self::TYPE_STRING,
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
			'column'     => 'duration',
			'property'   => 'duration',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'max_attempts',
			'property'   => 'maxAttempts',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'availability',
			'property'   => 'availability',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'anonymous_result',
			'property'   => 'anonymousResult',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'notify_author',
			'property'   => 'notifyAuthor',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));


		$this->hasJoin('category', array(
			'mapper'    => 'Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'category_id',
			'other_key' => 'id',
		));
		$this->hasJoin('user', array(
			'mapper'    => 'Schoolbox\\UserMapper',
			'this_key'  => 'user_id',
			'other_key' => 'id',
		));
		$this->hasJoin('work_type', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\WorkTypeMapper',
			'this_key'  => 'type',
			'other_key' => 'id',
		));
		$this->hasJoin('attempt', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\OnlineAssessment\\AttemptMapper',
			'multiple'  => true,
			'this_key'  => 'assess_id',
			'other_key' => 'assess_id',
		));

		$this->hasSoftDeleteField('deleted_at', self::TYPE_DATETIME);
	}

	/**
	 * Gets the list of activities related to online assessments.
	 *
	 * The following activities are retrieved:
	 *
	 * * New assessments within the categories
	 * * Closed assessments within the categories
	 *
	 * @param int   $user_id    The user for who activities are fetched for.
	 *                          Currently isn't used for this query, but may
	 *                          later be used to filter out assessments which a
	 *                          user has already submitted.
	 * @param int   $limit      The max number of activities to be fetched.
	 * @param array $categories The categories to fetch activities for.
	 *
	 * @return ActivityCollection
	 */
	public function getActivities($user_id, $limit, $categories) {
		$factory    = new ActivityFactory();
		$collection = new ActivityCollection();
		$queries = array(
			array(
				'event' => 'new',
				'query' => "
					SELECT
						{$this->getColumnString('a')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} a
					LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
					WHERE a.category_id IN {$this->getArrayPlaceholders($categories, 'category')}
					AND a.start < NOW()
					ORDER BY a.start DESC
					LIMIT :limit
				",
				'date_field' => 'start',
				'limit'      => $limit,
			),
			array(
				'event' => 'closed',
				'query' => "
					SELECT
						{$this->getColumnString('a')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} a
					LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
					WHERE a.category_id IN {$this->getArrayPlaceholders($categories, 'category')}
					AND a.finish < NOW()
					ORDER BY a.finish DESC
					LIMIT :limit
				",
				'date_field' => 'finish',
				'limit'      => $limit,
			),
		);

		foreach ($queries as $query) {
			$statement = $this->database->prepare($query['query']);
			$params    = $this->getArrayParameters($categories, 'category');
			if (!empty($query['limit'])) {
				$params['limit'] = $query['limit'];
			}
			foreach ($params as $key => $value) {
				$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
			}
			$statement->execute();

			//
			// Pass to activity collection instead? createFromOnlineAssessment?

			$get_method = $this->getGetMethodName($query['date_field']);
			foreach ($statement as $row) {
				$assess = $this->hydrate($row, 'a');
				$assess->setCategory($this->getJoinMapper('category')->hydrate($row, 'c'));
				$collection->add($factory->buildFromOnlineAssessment($assess, $query['event']));
			}
		}

		return $collection;
	}

	/**
	 * @deprecated TODO merge with duplicate code in SubmissionBoxMapper
	 * @param $semester
	 * @return array
	 */
	public function getSemesterMonths($semester) {
		$month_start = 1;
		$month_end   = 6;
		if ($semester == 2) {
			$month_start = 7;
			$month_end   = 12;
		}
		return array('start' => $month_start, 'end' => $month_end);
	}


	/**
	 * Retrieves
	 * @param int   $user_id
	 * @param array $categories
	 * @param int   $year
	 * @param int   $semester
	 * @return \Schoolbox\Collection
	 */
	public function getAllAssessments($user_id, $categories, $year = 0, $semester = 0) {

		$months = $this->getSemesterMonths($semester);
		$params = $this->getArrayParameters($categories, 'category');
		$params['year']        = $year ?: date('Y');
		$params['user_id']     = $user_id;
		$params['month_start'] = $months['start'];
		$params['month_end']   = $months['end'];



	}

	/**
	 * Gets all assessments that are due within the given date span.
	 *
	 * @param array $category_list  A list of category IDs to filter on.
	 * @param int   $from_timestamp A Unix timestamp to search from
	 * @param int   $to_timestamp   A Unix timestamp to search until
	 *
	 * @return ActivityCollection
	 */
	public function getDueActivities($category_list, $from_timestamp, $to_timestamp) {
		$factory    = new ActivityFactory();
		$collection = new ActivityCollection();
		if (empty($category_list)) {
			return $collection;
		}

		$cat_mapper  = $this->getJoinMapper('category');
		$user_mapper = $this->getJoinMapper('user');
		$query = "
			SELECT
				{$this->getColumnString('a')},
				{$cat_mapper->getColumnString('c')},
				{$user_mapper->getColumnString('u')}
			FROM {$this->getTable()} a
			LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
			LEFT JOIN {$this->getJoinSql('user',     'a', 'u')}
			WHERE a.category_id IN {$this->getArrayPlaceholders($category_list, 'category')}
			AND a.finish BETWEEN :from_date AND :to_date
			ORDER BY a.finish ASC
		";

		//
		// Build parameters
		$from_date = new \Alaress_Date_Format($from_timestamp, 'U');
		$to_date   = new \Alaress_Date_Format($to_timestamp,   'U');
		$params    = $this->getArrayParameters($category_list, 'category');
		$params['from_date'] = $from_date->getDatabaseDate();
		$params['to_date']   = $to_date->getDatabaseDate();
		$statement = $this->database->prepare($query);
		$statement->execute($params);

		//
		// Hydrate as activity list, then add category codes
		foreach ($statement as $row) {
			$box = $this->hydrate($row, 'a');
			$box->setUser($user_mapper->hydrate($row, 'u'))
				->setCategory($cat_mapper->hydrate($row, 'c'));
			$collection->add($factory->buildFromOnlineAssessment($box, 'closed'));
		}
		$cat_mapper->getJoinMapper('codes')->applyCodesToCategories(
			$collection,
			function($item) { return $item->getCategory(); }
		);
		return $collection;
	}

	/**
	 * Gets the list of upcoming activities for submission boxes.
	 *
	 * The following activities are retrieved:
	 *
	 * * Upcoming assessments, which the user hasn't submitted work for
	 *
	 * @param int   $user_id    The user for who activities are fetched for.
	 * @param int   $limit      The max number of activities to be fetched.
	 * @param array $categories The categories to fetch activities for.
	 *
	 * @return ActivityCollection
	 */
	public function getUpcomingActivities($user_id, $limit, $categories) {
		//
		// All submission boxes that are due in the future.
		$attempt_mapper = $this->getJoinMapper('attempt');
		$query = "
			SELECT
				{$this->getColumnString('a')},
				{$this->getJoinMapper('category')->getColumnString('c')},
				(SELECT COUNT(*) FROM {$attempt_mapper->getTable()} am
				 WHERE a.assess_id=am.assess_id AND am.user_id = :user_id
				) AS attempt_count
			FROM {$this->getTable()} a
			LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
			WHERE a.category_id IN {$this->getArrayPlaceholders($categories, 'category')}
			AND a.start < NOW()
			HAVING (attempt_count = 0 OR attempt_count <= a.max_attempts)
			ORDER BY a.start ASC
			LIMIT :limit
		";
		$statement = $this->database->prepare($query);
		$statement->bindValue('user_id', $user_id, \PDO::PARAM_INT);
		$statement->bindValue('limit',   $limit,   \PDO::PARAM_INT);
		foreach ($this->getArrayParameters($categories, 'category') as $param => $value) {
			$statement->bindValue($param, $value, \PDO::PARAM_INT);
		}
		$statement->execute();
		$assessments = $this->hydrateList($statement, 'sb', function($mapper, $model, $row) {
			$model->setCategory($mapper->getJoinMapper('category')->hydrate($row, 'c'));
			return $model;
		});

		$factory    = new ActivityFactory();
		$collection = new ActivityCollection();
		foreach ($assessments as $assessment) {
			$collection->add($factory->buildFromOnlineAssessment($assessment, 'upcoming'));
		}
		return $collection;
	}
}
