<?php
/**
 * Mapper for the OnlineAssessment model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use PDO;
use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;


class OnlineAssessmentMapper extends Mapper implements FeedableMapperInterface {

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
		// Probably redundant to store task ID in this table and assess ID in
		// task table. Oh well, it will make querying on whether a relation is a
		// task easier.
		$this->hasMap(array(
			'column'     => 'task_id',
			'property'   => 'task',
			'type'       => self::TYPE_MODEL,
			'join'       => 'task',
		));
		$this->hasMap(array(
			'column'     => 'task_instance_id',
			'property'   => 'taskInstance',
			'type'       => self::TYPE_MODEL,
			'join'       => 'task_instance',
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
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));
        $this->uses('completion_message', self::TYPE_STRING);
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
			'column'     => 'weight',
			'property'   => 'weight',
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
			'column'     => 'mark_visibility',
            'property'   => 'markVisibility',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deleted',
			'type'       => self::TYPE_DATETIME,
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
		$this->hasJoin('task', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Course\\TaskMapper',
			'this_key'  => 'task_id',
			'other_key' => 'id',
		));
		$this->hasJoin('attempt', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\OnlineAssessment\\AttemptMapper',
			'multiple'  => true,
			'this_key'  => 'assess_id',
			'other_key' => 'assess_id',
		));
		$this->hasJoin('task_instance', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Course\\Task\\InstanceMapper',
			'this_key'  => 'task_instance_id',
			'other_key' => 'id',
		));

        $this->joinsVia(
            'curriculum_node_for_assess',
            Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'assess_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

		$this->hasSoftDeleteDateField('deleted_at');

		$this->registerCallback('preSave', array($this, 'preSave'));
	}

	/**
	 * Pre-save callback.
	 * * ensures that work type is always WorkType::TYPE_ONLINE_ASSESSMENT
	 * * if a task, sets start time and finish time to current date to avoid
	 *   SQL errors
	 *
	 * @param  OnlineAssessment $object
	 * @return bool             Always returns true
	 */
	public function preSave(OnlineAssessment $object) {
		$object->setWorkType(WorkType::TYPE_ONLINE_ASSESSMENT);
		if (!$object->getId()) {
			$task    = $object->getTask();
			$task_id = ($task instanceof Task) ? $task->getId() : $task;
			if ($task_id) {
				$time = new \Alaress_Date_Format(time(), 'U');
				$object->setStart($time)->setFinish($time);
			}
		}
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
					AND a.deleted_at IS NULL
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
					AND a.deleted_at IS NULL
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
				$statement->bindParam($key, intval($value), PDO::PARAM_INT);
			}
			$statement->execute();

			//
			// Pass to activity collection instead? createFromOnlineAssessment?
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
	 * @return Collection
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
			AND a.deleted_at IS NULL
			AND a.task_id IS NULL
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
		$statement->bindValue('user_id', $user_id, PDO::PARAM_INT);
		$statement->bindValue('limit',   $limit,   PDO::PARAM_INT);
		foreach ($this->getArrayParameters($categories, 'category') as $param => $value) {
			$statement->bindValue($param, $value, PDO::PARAM_INT);
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

	/**
	 * Calculates and returns the statistics for the given online assessment
	 *
	 * @param $assess
	 * @return array
	 */
	public function getStatistics($assess) {
		if($assess instanceof OnlineAssessment) {
			$assess_id = $assess->getId();
		}
		else {
			$assess_id = $assess;
		}

		$sql = "
			SELECT
			    AVG(sum_mark) AS `mean`,
			    STDDEV(sum_mark) AS `stdev`,
			    MIN(sum_mark) AS `min`,
			    MAX(sum_mark) AS `max`
			FROM
			(
			  SELECT *
			  FROM
			  (
			      SELECT (SUM(mark) / SUM(aq.marks) * 100) AS sum_mark, ar.attempt_id, t0.user_id, t0.finish
			      FROM assess_response ar
			      INNER JOIN
			      (
			            SELECT attempt_id, user_id, finish
			            FROM assess_attempt aa
			            WHERE assess_id = :assess_id
			            AND aa.reviewed_at IS NOT NULL
			            AND aa.deleted_at IS NULL
			      ) t0
			      ON ar.attempt_id = t0.attempt_id
			      LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
			      GROUP BY t0.attempt_id
			      ORDER BY finish DESC
			  ) t1
			  GROUP BY user_id
			) t2;
		";

		$stmt = $this->database->prepare($sql);
		$stmt->execute(array('assess_id' => $assess_id));

		return $stmt->fetch(PDO::FETCH_ASSOC);

	}


	/**
	 * Returns the progress of the given online assess, ie. the number of submissions made and returns given
	 *
	 * @param $assess
	 * @return array
	 */
	public function getProgress($assess) {
		if($assess instanceof OnlineAssessment) {
			$assess_id = $assess->getId();
		}
		else {
			$assess_id = $assess;
		}

		$stmt = $this->database->prepare("
			SELECT COUNT(DISTINCT(user_id))
			FROM assess_attempt
			WHERE assess_id = :assess_id
		");

		$stmt->execute(array('assess_id' => $assess_id));
		$progress['submission_count'] = $stmt->fetchColumn(0);

		$progress['accepts_submissions'] = true;

		$stmt = $this->database->prepare("
			SELECT COUNT(DISTINCT(user_id))
			FROM assess_attempt
			WHERE assess_id = :assess_id
			AND reviewed_at IS NOT NULL
		");

		$stmt->execute(array('assess_id' => $assess_id));
		$progress['return_count'] = $stmt->fetchColumn(0);

		return $progress;
	}

	/**
	 * @param int $categoryId
	 * @param int $limit
	 * @param int $offset
	 * @param int $time
	 * @return Collection|OnlineAssessment[]
	 */
	public function findForFeed($categoryId, $limit, $offset = 0, $time = 0) {
		$time = date('Y-m-d H:i:s', $time ?: time());
		$limit = (int)$limit;
		$offset = (int)$offset;

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('a')},
				{$this->getJoinColumnString('category', 'c')},
				{$this->getJoinColumnString('work_type', 't')},
				{$this->getJoinColumnString('user', 'u')}
			FROM
				`{$this->getTable()}` `a`
				LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
				LEFT JOIN {$this->getJoinSql('work_type', 'a', 't')}
				LEFT JOIN {$this->getJoinSql('user', 'a', 'u')}
			WHERE
				`a`.`category_id` = :cat_id
				AND ( `a`.`start` < :time OR `a`.`finish` < :time )
				AND `a`.`deleted_at` IS NULL
			ORDER BY `a`.`start` DESC, `a`.`finish` DESC
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'cat_id' => $categoryId,
			'time'   => $time
		));

		$c = $this->createCollection();

		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			/** @var OnlineAssessment $quiz */
			$quiz = $this->hydrate($r, 'a');
			$quiz->setCategory($this->hydrateJoin($r, 'category',  'c'));
			$quiz->setUser(    $this->hydrateJoin($r, 'user',      'u'));
			$quiz->setWorkType($this->hydrateJoin($r, 'work_type', 't'));
			$c->add($quiz);
		}

		return $c;
	}


    /**
     * @param int $identifierValue
     * @throws Mapper\Exception
     * @return OnlineAssessment
     */
    public function findById($identifierValue)
    {
        $stmt = $this->getDatabase()->prepare("
            SELECT
                {$this->getColumnString('a')},
                {$this->getJoinColumnString('category', 'c')},
                {$this->getJoinColumnString('work_type', 't')},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('curriculum_node_for_assess', 'cn')},
                {$this->getJoinColumnString('curriculum_node_for_assess.level', 'cnl')},
                {$this->getJoinColumnString('curriculum_node_for_assess.level.source', 'cnls')},
                {$this->getJoinColumnString('curriculum_node_for_assess.area', 'cna')},
                {$this->getJoinColumnString('curriculum_node_for_assess.area.source', 'cnas')}
            FROM
                `{$this->getTable()}` `a`
                LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
                LEFT JOIN {$this->getJoinSql('work_type', 'a', 't')}
                LEFT JOIN {$this->getJoinSql('user', 'a', 'u')}
                LEFT JOIN {$this->getJoinSql('curriculum_node_for_assess', 'a', 'cn')}
                LEFT JOIN {$this->getJoinSql('curriculum_node_for_assess.level', 'cn', 'cnl')}
                LEFT JOIN {$this->getJoinSql('curriculum_node_for_assess.level.source', 'cnl', 'cnls')}
                LEFT JOIN {$this->getJoinSql('curriculum_node_for_assess.area', 'cn', 'cna')}
                LEFT JOIN {$this->getJoinSql('curriculum_node_for_assess.area.source', 'cna', 'cnas')}
            WHERE
                `a`.assess_id = :id
        ");

        $stmt->execute(array('id' => $identifierValue));

        $quiz = null;
        $curriculumNodes = [];
        foreach ($stmt as $row) {
            if (!($quiz instanceof OnlineAssessment)) {
                $quiz = $this->hydrate($row, 'a');
                $quiz->setCategory($this->hydrateJoin($row, 'category', 'c'));
                $quiz->setWorkType($this->hydrateJoin($row, 'work_type', 't'));
                $quiz->setUser($this->hydrateJoin($row, 'user', 'u'));
            }
            // Hydrate curriculum nodes
            /** @var Node\Model $curriculumNode */
            $curriculumNode = $this->getJoinMapper('curriculum_node_for_assess')
                ->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls');
            if ($curriculumNode->getId()) {
                $curriculumNodes[$curriculumNode->getId()] = $curriculumNode;
            }
        }
        $quiz->setCurriculumNodes(new Collection(array_values($curriculumNodes)));

        if (!($quiz instanceof OnlineAssessment)) {
            throw new Mapper\Exception("Instance of object not found");
        }
        $this->throwIfDeleted($quiz);
        return $quiz;
    }

}
