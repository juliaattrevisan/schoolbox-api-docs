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
use Schoolbox\ConfigLoader;
use Schoolbox\Curriculum\Node;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\Course\Task;

class SubmissionBoxMapper extends Schoolbox\Mapper implements FeedableMapperInterface
{

    /**
    * @var IConfigLoader
    */
    protected $config_loader;

    /**
    * @param  IConfigLoader $config_loader
    *
    * @return $this
    */
    public function setConfigLoader($config_loader)
    {
        $this->config_loader = $config_loader;
        return $this;
    }

    /**
    * @return IConfigLoader
    */
    public function getConfigLoader()
    {
        return $this->config_loader;
    }

    /**
    * Sets up the config loader in this object.
    */
    public function setup()
    {
        parent::setup();
        $this->setConfigLoader(new ConfigLoader());
    }

    protected function mapping()
    {
        $this->hasTable('submission_box');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'category',
            'property'   => 'category',
            'type'       => self::TYPE_MODEL,
            'join'       => 'category',
        ));
        $this->hasMap(array(
            'column'     => 'type',
            'property'   => 'workType',
            'type'       => self::TYPE_MODEL,
            'join'       => 'work_type',
        ));
        $this->hasMap(array(
            'column'     => 'name',
            'property'   => 'name',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'fid',
            'property'   => 'legacyFileId',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'owner',
            'property'   => 'owner',
            'type'       => self::TYPE_MODEL,
            'join'       => 'owner',
        ));
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
        $this->hasMap(
            array(
                'column'   => 'publish_date',
                'property' => 'publishDate',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasMap(array(
            'column'     => 'open_date',
            'property'   => 'openDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'date',
            'property'   => 'dueDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'late_date',
            'property'   => 'lateDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'accept_submission',
            'property'   => 'acceptOnline',
            'type'       => self::TYPE_BOOLEAN,
        ));
        $this->hasMap(array(
            'column'     => 'multi_submission',
            'property'   => 'acceptMultiple',
            'type'       => self::TYPE_BOOLEAN,
        ));
        $this->hasMap(array(
            'column'     => 'created',
            'property'   => 'created',
            'type'       => self::TYPE_DATETIME,
            'created_timestamp' => true,
        ));
        $this->hasMap(array(
            'column'     => 'weight',
            'property'   => 'weight',
            'type'       => self::TYPE_INTEGER,
        ));
        //
        // TODO add TYPE_COLLECTION
        $this->hasMap(array(
            'column'     => 'mark_type',
            'property'   => 'markType',
            'type'       => self::TYPE_MODEL,
            'join'       => 'mark_type',
        ));
        $this->hasMap(
            array(
                'column'   => 'submission_grade_group_id',
                'property' => 'gradeGroup',
                'type'     => self::TYPE_MODEL,
                'join'     => 'gradeGroup',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Group\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );
        $this->hasMap(array(
            'column'     => 'max_mark',
            'property'   => 'maxMark',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'rubric_result_set',
            'property'   => 'rubricResultSet',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'mark_visibility',
            'property'   => 'markVisibility',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'skip_unsubmitted',
            'property'   => 'skipUnsubmitted',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'deleted',
            'property'   => 'deleted',
            'type'       => self::TYPE_BOOLEAN,
        ));

        //
        // adds listeners:
        // buildSelectQuery('\\Schoolbox\\Assessment\\Component\\SubmissionBox')
        // undefinedMethod('hardDelete', function() {hard delete})
        // $this->hasSoftDeleteField('deleted');

        $this->hasJoin('category', array(
            'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
            //
            // Define as mapper fields instead?
            // this_property:     property for model object
            // this_key_property: property for key (if there is one) if not, get
            //                    ID value from this_property
            // other_property:    property in mapped model in other object that
            //                    links to this model
            'this_key'  => 'category',
            'other_key' => 'id',

            'this_property'  => 'category',
            'other_property' => 'id',
        ));
        $this->hasJoin('files', array(
            'multiple'  => true,
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\FileMapper',
            'this_key'  => 'id',
            'other_key' => 'box_id',
        ));

        $this->hasJoin('legacy_file', array(
            'multiple'  => true,
            'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
            'this_key'  => 'fid',
            'other_key' => 'id',
        ));

        $this->hasJoin('work_type', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\WorkTypeMapper',
            'this_key'  => 'type',
            'other_key' => 'id',
        ));

        $this->hasJoin('mark_type', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\MarkTypeMapper',
            'this_key'  => 'mark_type',
            'other_key' => 'mark_type_id',
        ));

        $this->hasJoin('owner', array(
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'owner',
            'other_key' => 'id',
        ));

        // TODO add Mapper->hasCollection?
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
        $this->hasJoin('task', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Course\\TaskMapper',
            'this_key'  => 'task_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('task_instance', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Course\\Task\\InstanceMapper',
            'this_key'  => 'task_instance_id',
            'other_key' => 'id',
        ));

        $this->joinsVia(
            'curriculum_node_for_assess',
            Node\Model::class,
            'id',
            'curriculum_node_id',
            'assess_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->hasSoftDeleteBooleanField('deleted');

        $this
            ->registerCallback('preSave', array($this, 'preSave'))
            ->registerCallback('postSave', array($this, 'postSave'))
        ;
    }

    /**
    * @throws Schoolbox\Mapper\Exception if submission box is not found
    * @param  int  $id
    * @return SubmissionBox
    */
    public function findById($id)
    {
        $box_file_mapper = $this->getJoinMapper('files');
        $file_mapper     = $box_file_mapper->getJoinMapper('file');
        $mime_mapper     = $file_mapper->getJoinMapper('allowedFileType');
        $worktype_mapper = $this->getJoinMapper('work_type');
        $marktype_mapper = $this->getJoinMapper('mark_type');

        $query = "
			SELECT
				{$this->getColumnString('sb')},
				{$worktype_mapper->getColumnString('wt')},
				{$marktype_mapper->getColumnString('mt')},
				{$this->getJoinColumnString('category', 'cat')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('work_type', 'sb', 'wt')}
			LEFT JOIN {$this->getJoinSql('mark_type', 'sb', 'mt')}
			LEFT JOIN {$this->getJoinSql('category',  'sb', 'cat')}
			WHERE sb.id=?
		";
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        $submission_box = false;

        if (($row = $statement->fetch()) !== false) {
            $submission_box = $this->hydrate($row, 'sb');
            $submission_box->setWorkType($worktype_mapper->hydrate($row, 'wt'));
            $submission_box->setMarkType($marktype_mapper->hydrate($row, 'mt'));
            $submission_box->setCategory($this->hydrateJoin($row, 'category', 'cat'));
        }

        if ($submission_box instanceof SubmissionBox) {
            // Get files? (This is more evidence separate file/link mapper isn't
            // required.)
            $query = "
				SELECT
					{$box_file_mapper->getColumnString('sm')},
					{$file_mapper->getColumnString('f')},
					{$mime_mapper->getColumnString('m')}
				FROM {$box_file_mapper->getTable()} sm
				LEFT JOIN {$box_file_mapper->getJoinSql('file', 'sm', 'f')}
				LEFT JOIN {$file_mapper->getJoinSql('allowedFileType', 'f', 'm')}
				WHERE sm.box_id=?
			";
            $statement = $this->database->prepare($query);
            $statement->execute(array($id));

            foreach ($statement as $row) {
                $box_file     = $box_file_mapper->hydrate($row, 'sm');
                /** @var Schoolbox\Storage $storage_file */
                $storage_file = $file_mapper->hydrate($row, 'f');
                $storage_file->setMimetype($mime_mapper->hydrate($row, 'm'));
                $box_file->setFile($storage_file);
                $submission_box->addFileLink($box_file);

            }
        }
        if (!($submission_box instanceof SubmissionBox)) {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }
        $this->throwIfDeleted($submission_box);
        return $submission_box;
    }

    /**
    * Pre-save method for submission boxes.
    * * converts legacy file to a new storage file
    * * Sets start, end and due time to now if this is a task
    *
    * @param SubmissionBox $object
    *
    * @return bool
    */
    public function preSave(SubmissionBox $object)
    {
        if ($legacy_file_id = $object->getLegacyFileId()) {
            $old_file = $this->getJoinMapper('legacy_file')->findById($legacy_file_id);
            $new_file = $this->getJoinMapper('legacy_file')->copyToStorage($old_file);

            $new_link = $this->getJoinMapper('files')->createModel();
            $new_link->setFile($new_file);
            $object->getFileLinks()->add($new_link);

            //
            // Remove legacy file link because it is no longer needed.
            $object->setLegacyFileId(null);
        }
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
    * Post-save method for submission boxes - saves new submission box files
    *
    * @param SubmissionBox $object
    * @param SubmissionBox $previous
    *
    * @return void
    */
    public function postSave(SubmissionBox $object, $previous = null)
    {
        foreach ($object->getFileLinks() as $box_file) {
            if ($box_file->hasFile()) {
                if (!$previous) {
                    $box_file->setId(null);
                }
                $box_file->setSubmissionBoxId($object->getId());
                $this->getJoinMapper('files')->save($box_file);
            }
        }
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
    * @param CategoryModel $category If set, restrict results to this category.
    *
    * @return Collection
    */
    public function getSubmittable(User $user, CategoryModel $category = null)
    {
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
            $model->setCategory($cat_mapper->hydrate($row, 'c'));
            return $model;
        });

        //
        // Load category codes too.
        $cat_mapper->getJoinMapper('codes')->applyCodesToCategories(
            $collection,
            function($item) { return $item->getCategory();
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
        $cat_mapper->getJoinMapper('codes')->applyCodesToCategories(
            $collection,
            function($item) { return $item->getCategory();
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
