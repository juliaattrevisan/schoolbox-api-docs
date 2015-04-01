<?php
/**
 * Mapper for the SubmissionBox model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;

use PDO;
use Schoolbox;
use Schoolbox\Assessment\Activity;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\Course\Task;

/**
 * Class SubmissionBoxMapper
 *
 * @package Schoolbox\Assessment\Component
 * @method SubmissionBox hydrate(array $row, $alias = '', $callback = null)
 */
class SubmissionBoxMapper extends Schoolbox\Mapper implements FeedableMapperInterface
{

    /**
     * @throws Schoolbox\Mapper\Exception
     */
    protected function mapping()
    {
        $this->hasTable('submission_box');

        $this->hasPrototype(new SubmissionBox());

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('publish_date', self::TYPE_DATETIME);
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('date', self::TYPE_DATETIME, null, 'dueDate');
        $this->uses('late_date', self::TYPE_DATETIME);
        $this->uses('accept_submission', self::TYPE_BOOLEAN, null, 'acceptOnline');
        $this->uses('multi_submission', self::TYPE_BOOLEAN, null, 'acceptMultiple');
        $this->uses('weight', self::TYPE_INTEGER);
        $this->uses('max_mark', self::TYPE_INTEGER);
        $this->uses('rubric_result_set', self::TYPE_INTEGER); // TODO this should be a MODEL join...
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->uses('skip_unsubmitted', self::TYPE_INTEGER);
        $this->uses('created', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted', self::TYPE_BOOLEAN, self::COLUMN_SOFTDELETE);

        $this->joins('category', Folder\Mapper::class);
        $this->joins('type', SubmissionBox\WorkTypeMapper::class, 'id', null, null, 'workType', 'work_type');
        $this->joins('owner', Schoolbox\UserMapper::class);
        $this->joins('task_id', Schoolbox\Assessment\Course\TaskMapper::class, 'id', null, null, 'task', 'task');
        $this->joins('task_instance_id', Task\InstanceMapper::class, 'id', null, null, 'taskInstance', 'task_instance');
        $this->joins('mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id');
        $this->joins('submission_grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'gradeGroup');

        $this->joinsVia('submission_box_file', Storage\Mapper::class, 'hash', 'storage_id', 'box_id', 'id', null, null, 'attachments');

        // joins that have no column definition
        $this->hasJoin('submission', array(
            'multiple'  => true,
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\SubmissionMapper',
            'this_key'  => 'id',
            'other_key' => 'box_id',
        ));
        $this->hasJoin('return', array(
            'multiple'  => true,
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\ReturnedWorkMapper',
            'this_key'  => 'id',
            'other_key' => 'box_id',
        ));

        $this->joinsVia(
            'curriculum_node_for_submission_box',
            Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'submission_box_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->hasSoftDeleteBooleanField('deleted');

        $this
            ->registerCallback('preSave', array($this, 'preSave'))
        ;
    }

    /**
    * @throws Schoolbox\Mapper\Exception if submission box is not found
    * @param  int  $id
    * @return SubmissionBox
    */
    public function findById($id)
    {
        /** @var Storage\Mapper $attach_mapper */
        $attach_mapper   = $this->getJoinMapper('submission_box_file');
        $worktype_mapper = $this->getJoinMapper('work_type');
        $marktype_mapper = $this->getJoinMapper('mark_type');

        // This is how you get ants!
        $query = "
			SELECT
				{$this->getColumnString('sb')},
				{$worktype_mapper->getColumnString('wt')},
				{$marktype_mapper->getColumnString('mt')},
				{$this->getJoinColumnString('category', 'cat')},
                {$this->getJoinColumnString('curriculum_node_for_submission_box', 'cn')},
                {$this->getJoinColumnString('curriculum_node_for_submission_box.level', 'cnl')},
                {$this->getJoinColumnString('curriculum_node_for_submission_box.level.source', 'cnls')},
                {$this->getJoinColumnString('curriculum_node_for_submission_box.area', 'cna')},
                {$this->getJoinColumnString('curriculum_node_for_submission_box.area.source', 'cnas')},
				{$attach_mapper->getColumnString('at')},
				{$attach_mapper->getJoinColumnString('allowedFileExtension', 'mime')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('work_type', 'sb', 'wt')}
			LEFT JOIN {$this->getJoinSql('mark_type', 'sb', 'mt')}
			LEFT JOIN {$this->getJoinSql('category',  'sb', 'cat')}
			LEFT JOIN {$this->getJoinSql('submission_box_file', 'sb', 'at')}
			LEFT JOIN {$attach_mapper->getJoinSql('allowedFileExtension', 'at', 'mime')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_submission_box', 'sb', 'cn')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_submission_box.level', 'cn', 'cnl')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_submission_box.level.source', 'cnl', 'cnls')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_submission_box.area', 'cn', 'cna')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_submission_box.area.source', 'cna', 'cnas')}
			WHERE sb.id=?
		";
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        $submission_box = false;

        $curriculumNodes = [];
        foreach ($statement as $row) {
            if (!($submission_box instanceof SubmissionBox)) {
                $submission_box = $this->hydrate($row, 'sb');
                $submission_box->setWorkType($worktype_mapper->hydrate($row, 'wt'));
                $submission_box->setMarkType($marktype_mapper->hydrate($row, 'mt'));
                $submission_box->setCategory($this->hydrateJoin($row, 'category', 'cat'));
            }
            // Hydrate curriculum nodes
            /** @var Node\Model $curriculumNode */
            $curriculumNode = $this->getJoinMapper('curriculum_node_for_submission_box')
                ->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls');
            if ($curriculumNode->getId()) {
                $curriculumNodes[$curriculumNode->getId()] = $curriculumNode;
            }
        }

        if ($submission_box instanceof SubmissionBox) {
            $submission_box->setCurriculumNodes(new Collection(array_values($curriculumNodes)));

            $storage = $attach_mapper->hydrate($row, 'at');
            $storage->setMimetype($attach_mapper->hydrateJoin($row, 'allowedFileExtension', 'mime'));
            $submission_box->addAttachment($storage);
        }

        if (!($submission_box instanceof SubmissionBox)) {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }
        $this->throwIfDeleted($submission_box);
        return $submission_box;
    }

    /**
    * Pre-save method for submission boxes.
    * * Sets start, end and due time to now if this is a task
    *
    * @param SubmissionBox $object
    *
    * @return bool
    */
    public function preSave(SubmissionBox $object)
    {
        if (!$object->getId()) {
            $task    = $object->getTask();
            $task_id = ($task instanceof Task) ? $task->getId() : $task;
            if ($task_id) {
                $time = new \Alaress_Date_Format(time(), 'U');
                $object->setPublishDate($time);
                $object->setOpenDate($time);
                $object->setDueDate($time);
                $object->setLateDate($time);
            }
        }
        return true;
    }


    /**
    * Conversion of SubmissionBoxHandler->getAllYearsByUser().
    *
    * @param int $user_id The user ID to retrieve for.
    *
    * @return array
    */
    public function getAllYearsByUser($user_id)
    {
        $query = "
			SELECT DISTINCT DATE_FORMAT(submitted, '%Y') AS submitted_year
			FROM student_submission
			WHERE owner = ?
			ORDER BY submitted DESC";

        $statement = $this->database->prepare($query);
        $statement->execute(array($user_id));

        $years = array();
        foreach ($statement as $row) {
            $years[] = $row['submitted_year'];
        }
        return $years;
    }


    /**
    * Gets the months that are a part of this semester.
    *
    * @todo move this somewhere where other semester reliant code can use it
    *
    * @param $semester
    *
    * @return array
    */
    public function getSemesterMonths($semester)
    {
        $month_start = 1;
        $month_end   = 6;
        if ($semester == 2) {
            $month_start = 7;
            $month_end   = 12;
        }
        return array('start' => $month_start, 'end' => $month_end);
    }


    /**
    * Conversion of SubmissionBoxHandler->getAllYearsByUser().
    *
    * @todo move to a more appropriate class?
    *
    * This could get a list of categories which have submissions, and pass to
    * CategoryMapper->getByIds()?
    *
    * @param int $user_id  The user ID to retrieve for.
    * @param int $year     The year to fetch for.
    * @param int $semester The semester to fetch for.
    *
    * @return array
    */
    public function submittedToCategoryList($user_id, $year, $semester)
    {
        $query = "
			SELECT category.id, category.name, GROUP_CONCAT(cc.code) AS code
			FROM category
			LEFT JOIN categoryCode cc ON category.id = cc.categoryID AND cc.deleted_at IS NULL
			WHERE category.id IN (
				SELECT DISTINCT sb.category
				FROM submission_box sb
				LEFT JOIN student_submission ss ON ss.box_id = sb.id
				LEFT JOIN submission_return  sr ON sr.box_id = sb.id
				WHERE (ss.owner = :user_id OR sr.owner = :user_id)
				AND sb.deleted IS FALSE
				AND sb.task_id IS NULL
				AND (YEAR(sb.`date`) = :year AND MONTH(sb.`date`) BETWEEN :month_start AND :month_end)
			)
			GROUP BY category.id
			ORDER BY category.name";

        $months    = $this->getSemesterMonths($semester);
        $statement = $this->database->prepare($query);

        $statement->execute(array(
            'user_id'     => $user_id,
            'year'        => $year,
            'month_start' => $months['start'],
            'month_end'   => $months['end'],
        ));
        return $statement->fetchAll();
    }


    /**
    * Conversion of SubmissionBoxHandler->getAllDueWork().
    *
    * @todo Implement this as an activity list instead of an array return
    *
    * @param int   $user_id    The user ID to retrieve for.
    * @param array $categories The categories to search for.
    * @param int   $year       The year to fetch for.
    * @param int   $semester   The semester to fetch for.
    *
    * @return array
    */
    public function getAllDueWork($user_id, $categories, $year = 0, $semester = 0)
    {
        //
        // Submission box: hydrate with category, student submissions and returns.
        $months = $this->getSemesterMonths($semester);
        $params = $this->getArrayParameters($categories, 'category');
        $params['year']        = $year ?: date('Y');
        $params['user_id']     = $user_id;
        $params['month_start'] = $months['start'];
        $params['month_end']   = $months['end'];

        $query = "
			SELECT
			b.id AS bid,
			s.id AS sid,
			r.id AS rid,
			UNIX_TIMESTAMP(b.late_date) AS lateDate,
			UNIX_TIMESTAMP(b.date) AS dueDate,
			c.name AS categoryName,
			b.name,
			b.description,
			f.name AS type,
			r.mark,
			r.norm_mark,
			r.comment,
			b.mark_visibility,
			b.created, s.submitted, r.returned
			FROM submission_box b
			LEFT JOIN category c ON c.id = b.category
			LEFT JOIN student_submission s ON s.box_id = b.id AND (s.owner = :user_id OR s.owner IS NULL)
			LEFT JOIN submission_return  r ON r.box_id = b.id AND (r.owner = :user_id OR r.owner IS NULL)
			LEFT JOIN lu_submission_type f ON b.type = f.id
			WHERE b.deleted = 0
			AND b.category IN {$this->getArrayPlaceholders($categories, 'category')}
			AND YEAR(b.date) = :year
			AND MONTH(b.date) BETWEEN :month_start AND :month_end
			AND b.task_id IS NULL
			GROUP BY b.id
			ORDER BY categoryName, b.created DESC, s.submitted DESC, r.returned DESC
		";

        $statement = $this->database->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /**
    * Gets the list of activities related to submission boxes.
    *
    * The following activities are retrieved:
    *
    * * New submission boxes
    * * Overdue submission boxes, which the user hasn't submitted work for
    *   (The implementation in Submission->getActivityLog() appears to fetch
    *    submission boxes which the user has submitted work for)
    * * Closed submission boxes
    *
    * @param int   $user_id    The user for who activities are fetched for.
    * @param int   $limit      The max number of activities to be fetched.
    * @param array $categories The categories to fetch activities for.
    *
    * @return ActivityCollection
    */
    public function getActivities($user_id, $limit, $categories)
    {
        $factory    = new ActivityFactory();
        $collection = new ActivityCollection();
        $queries = array(
            array(
                'event' => 'new',
                'query' => "
					SELECT
						{$this->getColumnString('sb')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} sb
					LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
					WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
					AND sb.open_date < NOW()
					AND sb.deleted IS FALSE
					AND sb.task_id IS NULL
					ORDER BY sb.open_date DESC
					LIMIT :limit
				",
                'date_field' => 'openDate',
                'limit'      => $limit,
            ),
            array(
                //
                // Overdue: shouldn't this be for items which a student hasn't
                // submitted, instead of one which they have?
                'event' => 'overdue',
                'query' => "
					SELECT
						{$this->getColumnString('sb')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} sb
					LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
					LEFT JOIN {$this->getJoinSql('submission', 'sb', 'sm')} AND sm.owner = :user_id
					WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
					AND sm.id IS NULL
					AND sb.deleted IS FALSE
					AND sb.task_id IS NULL
					AND sb.accept_submission = 1
					AND sb.date < NOW()
					ORDER BY sb.date DESC
					LIMIT :limit
				",
                'date_field' => 'dueDate',
                'limit'      => $limit,
                'user_id'    => $user_id,
            ),
            array(
                'event' => 'closed',
                'query' => "
					SELECT
						{$this->getColumnString('sb')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} sb
					LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
					WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
					AND sb.deleted IS FALSE
					AND sb.task_id IS NULL
					AND sb.accept_submission = 1
					AND sb.late_date < NOW()
					ORDER BY sb.late_date DESC
					LIMIT :limit
				",
                'date_field' => 'lateDate',
                'limit'      => $limit,
            ),
        );

        foreach ($queries as $query) {
            $statement = $this->database->prepare($query['query']);
            $params    = $this->getArrayParameters($categories, 'category');
            if (!empty($query['limit'])) {
                $params['limit'] = $query['limit'];
            }
            if (!empty($query['user_id'])) {
                $params['user_id'] = $query['user_id'];
            }
            foreach ($params as $key => $value) {
                $statement->bindParam($key, intval($value), PDO::PARAM_INT);
            }
            $statement->execute();

            foreach ($statement as $row) {
                $box = $this->hydrate($row, 'sb');
                $box->setCategory($this->getJoinMapper('category')->hydrate($row, 'c'));
                $collection->add($factory->buildFromSubmissionBox($box, $query['event']));
            }
        }

        return $collection;
    }

    /**
     * Gets the list of upcoming activities for submission boxes.
     *
     * The following activities are retrieved:
     *
     * * Upcoming submission boxes, which the user hasn't submitted work for
     *
     * @param int|null  $user_id    The user for whom activities are fetched, or null if any.
     * @param int       $limit      The max number of activities to be fetched, or 0 if all.
     * @param array|int $categories The category/ies to fetch activities for.
     *
     * @return ActivityCollection|Activity[]
     */
    public function getUpcomingActivities($user_id, $limit, $categories)
    {
        if (!is_array($categories)) {
            $categories = array($categories);
        }
        //
        // All submission boxes that are due in the future.
        $submission_mapper = $this->getJoinMapper('submission');

        $lim = $limit ? 'LIMIT :limit' : '';

        if (is_null($user_id)) {
            $selectCount = '';
            $having = '';
        } else {
            // NOTICE THE COMMA!
            $selectCount = "
                , (
                  SELECT COUNT(*) FROM {$submission_mapper->getTable()} sm
                  WHERE sb.id=sm.submission_box_id AND sm.owner = :user_id
                )
                AS submission_count
            ";
            $having = "HAVING (submission_count = 0 OR sb.multi_submission IS TRUE)";
        }

        $query = "
			SELECT
				{$this->getColumnString('sb')},
				{$this->getJoinMapper('category')->getColumnString('c')}
				$selectCount
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
			WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
			AND sb.late_date > NOW()
			AND sb.deleted IS FALSE
			AND sb.task_id IS NULL
			$having
			ORDER BY sb.date ASC
			$lim
		";
        $statement = $this->database->prepare($query);

        if (!is_null($user_id)) {
            $statement->bindValue('user_id', $user_id, PDO::PARAM_INT);
        }

        if ($limit) {
            $statement->bindValue('limit', intval($limit), PDO::PARAM_INT);
        }

        foreach ($this->getArrayParameters($categories, 'category') as $param => $value) {
            $statement->bindValue($param, $value, PDO::PARAM_INT);
        }
        $statement->execute();
        $boxes = $this->hydrateList($statement, 'sb', function($mapper, $model, $row) {
            /**
             * @var SubmissionBox $model
             * @var self $mapper
             */
            $model->setCategory($mapper->getJoinMapper('category')->hydrate($row, 'c'));
            return $model;
        });

        $factory    = new ActivityFactory();
        $collection = new ActivityCollection();
        foreach ($boxes as $box) {
            $collection->add($factory->buildFromSubmissionBox($box, 'upcoming'));
        }
        return $collection;
    }


    /**
    * Retrieves all submission boxes which take online submissions within a
    * given category.
    *
    * @param User     $user     The user to filter submissions for
    * @param Folder\Model $category If set, restrict results to this category.
    *
    * @return Collection
    */
    public function getSubmittable(User $user, Folder\Model $category = null)
    {
        /** @var Folder\Mapper $cat_mapper */
        $cat_mapper = $this->getJoinMapper('category');

        //
        // only use the external user if the external DB is set up.
        if ($this->getConfigLoader()->get('external_enable')) {
            $cat_mapper->setExternalUser(new \External_User());
        }
        $user_categories = $cat_mapper->findForUser($user, true);
        $cat_ids         = array();
        foreach ($user_categories as $cat) {
            $cat_ids[] = $cat->getId();
        }

        if ($category) {
            $cat_ids = array_intersect($cat_ids, array($category->getId()));
        }

        //
        // Get list of valid categories for the given user.
        $params      = array();
        $where_parts = array(
            'sb.open_date <= NOW()',
            'sb.late_date > NOW()',
            'sb.accept_submission IS TRUE',
            'sb.deleted IS FALSE',
            'sb.task_id IS NULL',
        );
        if ($cat_ids) {
            $params        = $this->getArrayParameters($cat_ids, 'cat');
            $where_parts[] = 'sb.category IN '.$this->getArrayPlaceholders($cat_ids, 'cat');
        }
        $where = implode(' AND ', $where_parts);
        $query = "
			SELECT {$this->getColumnString('sb')}, {$cat_mapper->getColumnString('c')}
			FROM {$this->getTable()} sb
			INNER JOIN {$this->getJoinSql('category', 'sb', 'c')}
			WHERE $where
			ORDER BY c.name ASC
		";
        $statement = $this->database->prepare($query);
        $statement->execute($params);

        //
        // Load categories.
        $collection = $this->hydrateList($statement, 'sb', function($mapper, $model, $row) use ($cat_mapper) {
            /** @var SubmissionBox $model */
            $model->setCategory($cat_mapper->hydrate($row, 'c'));
            return $model;
        });

        //
        // Load category codes too.
        /** @var Folder\Code\Mapper $code_mapper */
        $code_mapper = $cat_mapper->getJoinMapper('codes');
        $code_mapper->applyCodesToCategories($collection,
            function ($item) {
                /** @var SubmissionBox $item */
                return $item->getCategory();
            }
        );
        return $collection;
    }

    /**
    * Gets all submission boxes that are due within the given date span.
    *
    * @param array $category_list  A list of category IDs to filter on.
    * @param int   $from_timestamp A Unix timestamp to search from
    * @param int   $to_timestamp   A Unix timestamp to search until
    *
    * @return ActivityCollection
    */
    public function getDueActivities($category_list, $from_timestamp, $to_timestamp)
    {
        $factory    = new ActivityFactory();
        $collection = new ActivityCollection();
        if (empty($category_list)) {
            return $collection;
        }

        $cat_mapper  = $this->getJoinMapper('category');
        $user_mapper = $this->getJoinMapper('owner');
        $query = "
			SELECT
				{$this->getColumnString('sb')},
				{$cat_mapper->getColumnString('c')},
				{$user_mapper->getColumnString('u')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
			LEFT JOIN {$this->getJoinSql('owner',    'sb', 'u')}
			WHERE sb.category IN {$this->getArrayPlaceholders($category_list, 'category')}
			AND sb.date BETWEEN :from_date AND :to_date
			AND sb.deleted IS FALSE
			AND sb.task_id IS NULL
			ORDER BY sb.date ASC
		";

        //
        // Build parameters
        $from_date = new \Alaress_Date_Format($from_timestamp, 'U');
        $to_date   = new \Alaress_Date_Format($to_timestamp, 'U');
        $params    = $this->getArrayParameters($category_list, 'category');
        $params['from_date'] = $from_date->getDatabaseDate();
        $params['to_date']   = $to_date->getDatabaseDate();
        $statement = $this->database->prepare($query);
        $statement->execute($params);

        //
        // Hydrate as activity list, then add category codes
        foreach ($statement as $row) {
            $box = $this->hydrate($row, 'sb');
            $box->setOwner($user_mapper->hydrate($row, 'u'));
            $box->setCategory($cat_mapper->hydrate($row, 'c'));
            $collection->add($factory->buildFromSubmissionBox($box, 'overdue'));
        }
        /** @var Folder\Code\Mapper $code_mapper */
        $code_mapper = $cat_mapper->getJoinMapper('codes');
        $code_mapper->applyCodesToCategories($collection,
            function ($item) {
                /** @var SubmissionBox $item */
                return $item->getCategory();
            }
        );
        return $collection;
    }

    /**
    * Calculates and returns the statistics of the given submission box
    *
    * @param $submission_box
    * @return array
    */
    public function getStatistics($submission_box)
    {
        if ($submission_box instanceof SubmissionBox) {
            $submission_box_id = $submission_box->getId();
        } else {
            $submission_box_id = $submission_box;
        }

        // Fetch only the most recent return for each student.
        $sql = "
			SELECT
				AVG(t0.norm_mark) AS `mean`,
				STDDEV(t0.norm_mark) AS `stdev`,
				MIN(t0.norm_mark) AS `min`,
				MAX(t0.norm_mark) AS `max`
			FROM (
				SELECT * FROM (
					SELECT *
					FROM submission_return sr
					WHERE sr.box_id = :box_id
					ORDER BY sr.returned DESC
				) t1
				GROUP BY t1.owner
				HAVING t1.norm_mark IS NOT NULL
			) t0;
		";

        $stmt = $this->database->prepare($sql);
        $stmt->execute(array('box_id' => $submission_box_id));

        return $stmt->fetch(PDO::FETCH_ASSOC);

    }


    /**
    * Returns the progress of the given submission box, ie. the number of submissions made and returns given
    *
    * @param $submission_box
    * @return array
    */
    public function getProgress($submission_box)
    {
        if ($submission_box instanceof SubmissionBox) {
            $submission_box_id = $submission_box->getId();
        } else {
            // we need both elements
            $submission_box_id = $submission_box;
            $submission_box = $this->findById($submission_box_id);
        }

        $stmt = $this->database->prepare("
			SELECT COUNT(DISTINCT(owner))
			FROM student_submission
			WHERE box_id = :box_id
		");

        $stmt->execute(array('box_id' => $submission_box_id));
        $progress['submission_count'] = $stmt->fetchColumn(0);

        $progress['accepts_submissions'] = $submission_box->getAcceptOnline();

        $stmt = $this->database->prepare("
			SELECT COUNT(DISTINCT(owner))
			FROM submission_return
			WHERE box_id = :box_id
		");

        $stmt->execute(array('box_id' => $submission_box_id));
        $progress['return_count'] = $stmt->fetchColumn(0);

        return $progress;

    }


    /**
    * @param int $categoryId
    * @param int $limit
    * @param int $offset
    * @param int $time
    * @return Collection|SubmissionBox[]
    */
    public function findForFeed($categoryId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ?: time());
        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('sb')},
				{$this->getJoinColumnString('category', 'c')},
				{$this->getJoinColumnString('work_type', 't')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `sb`
				LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
				LEFT JOIN {$this->getJoinSql('work_type', 'sb', 't')}
				LEFT JOIN {$this->getJoinSql('owner', 'sb', 'u')}
			WHERE
				`sb`.`category` = :cat_id
				AND ( `sb`.`open_date` < :time OR `sb`.`date` < :time )
				AND `sb`.`deleted` = 0
			ORDER BY `sb`.`open_date` DESC, `sb`.`date` DESC
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(array(
            'cat_id' => $categoryId,
            'time'   => $time
        ));

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var SubmissionBox $box */
            $box = $this->hydrate($r, 'sb');
            $box->setCategory($this->hydrateJoin($r, 'category', 'c'));
            $box->setOwner($this->hydrateJoin($r, 'owner', 'u'));
            $box->setWorkType($this->hydrateJoin($r, 'work_type', 't'));
            $c->add($box);
        }

        return $c;
    }

    /**
     * Port of SubmissionStudent handler's securityCheck() method.
     *
     * @throws Schoolbox\Resource\Exception
     *
     * @param SubmissionBox $box
     * @param User          $user
     *
     * @return bool
     */
    public function checkUploadability(SubmissionBox $box, User $user)
    {
        // Are online submissions accepted?
        if (!$box->getAcceptOnline()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box does not accept online submission.',
                Schoolbox\Resource\Exception::CODE_NO_ONLINE_SUBMISSION
            );
        }

        // Does a (non-draft) submission already exist for this student?
        $boxId  = $box->getId();
        $userId = $user->getId();
        $stmt   = $this->database->prepare(
            "SELECT id FROM `student_submission` WHERE box_id=? AND owner=? AND draft IS FALSE"
        );
        $stmt->execute(array($boxId, $userId));
        $submitted = ($stmt->rowCount() > 0);
        if ($submitted && !$box->getAcceptMultiple()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box does not accept multiple submissions.',
                Schoolbox\Resource\Exception::CODE_NO_MULTIPLE_SUBMISSION
            );
        }

        // Is the submission late (after overdue time)?
        $now = new \DateTime('now');
        if ($box->getLateDate()->getUnixTimestamp() < $now->getTimestamp()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box no longer accepts submissions.',
                Schoolbox\Resource\Exception::CODE_SUBMISSION_CLOSED
            );
        }

        // Is the student involved in this group?
        // (Need much better checking than this, this abuses session variables)

        /** @var Schoolbox\Resource\CategoryMapper $catMap */
        $catMap = $this->getJoinMapper('category');
        $catMap->setExternalUser(new \External_User());
        $categories = array();
        foreach ($catMap->findForUser($user, true) as $cat) {
            $categories[] = $cat->getId();
        }
        if (!in_array($box->getCategory()->getId(), $categories)) {
            // 403 forbidden error
            throw new Schoolbox\Resource\Exception('Access denied', Schoolbox\Resource\Exception::CODE_ACCESS_DENIED);
        }

        // Is the submission early (before open time)?
        if ($box->getOpenDate()->getUnixTimestamp() > $now->getTimestamp()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box does not accept submissions yet.',
                Schoolbox\Resource\Exception::CODE_SUBMISSION_NOT_YET_OPEN
            );
        }
        return true;
    }
}
