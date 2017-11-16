<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Component_Instance;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Course;
use Schoolbox\Collection;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Mapper for the unit instance model.
 *
 * @deprecated Use \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper
 *             and its related business domain classes instead.
 */
class UnitInstanceMapper extends BaseMapper {

    protected function mapping() {
        $this->hasTable('course_unit_implementation');
        $this->hasModel(UnitInstance::class);
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'title',
            'property'   => 'title',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'open_date',
            'property'   => 'openDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'due_date',
            'property'   => 'dueDate',
            'type'       => self::TYPE_DATETIME,
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
        $this->hasJoin('task', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Course\\Task\\InstanceMapper',
            'this_key'  => 'id',
            'other_key' => 'course_unit_instance_id',
            'multiple'  => true,
        ));

        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerCallback('postSave',       array($this, 'postSave'));
        $this->registerCallback('postDelete',     array($this, 'postDelete'));
        $this->registerCallback('postSoftDelete', array($this, 'postDelete'));
    }

    /**
     * Find the course, whose units from which a Unit
     * $unitInstance was spawned
     *
     * @param UnitInstance $unitInstance
     * @return Course\DeprecatedModel
     * @throws BaseMapper\Exception
     */
    public function findCourseForUnit(UnitInstance $unitInstance)
    {
        $unit = $unitInstance->getUnitCategory();

        if (!$unit->getParent() instanceof Folder) {
            // dynamic hydration.....
            try {
                $unit = Course\Unit\DeprecatedMapper::getInstance()->findById($unit->getId());
            } catch (BaseMapper\Exception\Deleted $e) {
                // I reject your deletion of this object
                $unit = $e->getDeletedObject();
            }
        }

        return Course\DeprecatedMapper::getInstance()->findById($unit->getParent()->getId());
    }

    /**
     * Find all folders with unit instances derived from units
     * which belong to the specified course.
     *
     * Optionally filter by unit instances with due dates between
     * $to and $from, so that you can get statistics specific to terms.
     *
     * @param Folder $course
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return Collection
     * @throws BaseMapper\Exception
     */
    public function findAllFoldersUsingCourse(
        Folder $course,
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null
    ) {
        $folderMapper = $this->getJoinMapper('unit_category');
        $code_mapper = $this->getJoinMapper('category')->getJoinMapper('codes');

        // Allow null dates in from/to
        $dateFilter = '';
        $params     = ['folderId' => $course->getId(),];
        if ($from instanceof Alaress_Date_Format && !$from->isNull()) {
            $dateFilter      = $dateFilter . ' AND ui.due_date >= :from';
            $params['from']  = $from->getDatabaseDate();
        }
        if ($to instanceof Alaress_Date_Format && !$to->isNull()) {
            $dateFilter    = $dateFilter . ' AND ui.due_date <= :to';
            $params['to']  = $to->getDatabaseDate();
        }

        $query = "
            SELECT
                {$folderMapper->getColumnString('c')},
                {$code_mapper->getColumnString('cc')}
            FROM {$this->getTable()} `ui`
            LEFT JOIN {$this->getJoinSql('unit_category', 'ui', 'uc')}
            LEFT JOIN {$this->getJoinSql('course_folder', 'uc', 'cf')}
            INNER JOIN {$this->getJoinSql('category', 'ui', 'c')}
            LEFT JOIN folder_code cc ON c.id = cc.folder_id
            WHERE `cf`.`id` = :folderId
                AND c.deleted_at IS NULL
                AND ui.deleted_at IS NULL
                AND uc.deleted_at IS NULL
                $dateFilter
            GROUP BY `c`.`id`
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $collection = new Collection();

        while ($row = $statement->fetch()) {
            $existingCat = $collection->filterBy('id', $row['c.id']);
            if ($existingCat->count()) {
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getFolder()->getId()) {
                    $existingCat->first()->addCode($code);
                }
            }
            else {
                $course = $folderMapper->hydrate($row, 'c');
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getFolder()->getId()) {
                    $course->addCode($code);
                }
                $collection->add($course);
            }
        }
        return $collection;
    }

    /**
     * A macro version of findAllCoursesUsedInFolder()
     *
     * Pass it an array of folderIds and this will give you a list of
     * associated course folders, all in one query, rather than multiple
     *
     * Returns an associative collection of folderId-courseFolderCollection
     *
     * (or you could have had a folder-course relation in the db)
     *
     * @param $folderIds
     * @return \Schoolbox\Collection
     */
    public function findAllCoursesUsedInFolders($folderIds) {
        $folderMapper = $this->getJoinMapper('unit_category');

        $query = "
            SELECT
                {$this->getColumnString('ui')},
                {$folderMapper->getColumnString('cf')}
            FROM {$this->getTable()} `ui`
            LEFT JOIN {$this->getJoinSql('unit_category', 'ui', 'uc')}
            LEFT JOIN {$this->getJoinSql('course_folder', 'uc', 'cf')}
            WHERE `ui`.`category_id` IN {$this->getArrayPlaceholders($folderIds)}
            AND ui.deleted_at IS NULL
            GROUP BY `cf`.`id`, ui.`category_id`
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($this->getArrayParameters($folderIds));

        $collection = new Collection();

        while ($row = $statement->fetch()) {
            $unitInstance = $this->hydrate($row, 'ui');
            $courseFolder = $folderMapper->hydrate($row, 'cf');

            if ($collection->getOffset($unitInstance->getCategory()->getId()) instanceof Collection) {
                $collection->getOffset($unitInstance->getCategory()->getId())->add($courseFolder);
            }
            else {
                $subcollection = new Collection();
                $subcollection->add($courseFolder);

                $collection->setOffset($unitInstance->getCategory()->getId(), $subcollection);
            }
        }

        return $collection;
    }

    /**
     * Find a list of Units whose Course Outlines no longer exist
     *
     * @param $folderId
     * @return Collection
     */
    public function findRestorableUnits($folderId)
    {
        $query = "
            SELECT {$this->getColumnString('cui')}
            FROM {$this->getTable()} cui
            LEFT JOIN component_instance ON cui.`component_id` = component_instance.`id`
            WHERE component_instance.id IS NULL
            AND category_id = :folderId
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'folderId' => $folderId
        ]);

        return $this->hydrateList($statement, 'cui');
    }

    /**
     * Post-creation callback:
     * * save rubrics that belong to either the unit or course homepage
     * * save a new task instance for each task in this unit.
     *
     * Existing task instances for this course are no longer updated here,
     * because users generally didn't want to clobber the open or due date of
     * due work items or quizzes associated with a task.
     *
     * @param  UnitInstance $object
     * @param  UnitInstance $previous
     * @return void
     */
    public function postSave(UnitInstance $object, $previous = null) {
        // Create tasks for this item (if this item was only just saved)
        if (!$previous) {
            $criterionMapper = SubmissionBox\Rubric\CriterionMapper::getInstance();
            $criterionMapper->copyFromCategory(
                $object->getUnitCategory()->getParent()->getId(),
                $object->getCategory()->getId()
            );
            $criterionMapper->copyFromCategory(
                $object->getUnitCategory()->getId(),
                $object->getCategory()->getId()
            );
            $task_list = TaskMapper::getInstance()->findByColumn(
                'category_id',
                $object->getUnitCategory()->getId()
            );
            $task_instance_collection = new Collection();
            foreach ($task_list as $task) {
                $task_instance = new Task\Instance();
                $task_instance
                    ->setUnitInstance($object)
                    ->setTask($task)
                    ->setOwner($object->getOwner() ?: $_SESSION['user'])
                ;
                $task_instance_collection->add($task_instance);
            }
            Task\InstanceMapper::getInstance()->save($task_instance_collection);
        }
    }

    /**
     * Post-delete callback - deletes all task instances that are related to
     * this item.
     *
     * @param UnitInstance $object
     */
    public function postDelete(UnitInstance $object) {
        // Delete any related task instances
        $task_instance_mapper = Task\InstanceMapper::getInstance();
        $task_instance_mapper->delete(
            $task_instance_mapper->findByColumn(
                'course_unit_instance_id',
                $object->getId()
            )
        );
    }


    /**
     * Saves a instance of $task into unit instance $unit.
     *
     * @param UnitInstance $unit
     * @param Task         $task
     * @param User         $owner
     */
    public function saveTaskIntoUnitInstance(UnitInstance $unit, Task $task, User $owner) {
        // Protection for if the assessment or box aren't objects at all.
        // TODO: make the hydrator at least hydrate null objects?
        if (!($task->getQuiz() instanceof Quiz)) {
            $task->setQuiz(new Quiz());
        }
        if (!($task->getSubmissionBox() instanceof SubmissionBox)) {
            $task->setSubmissionBox(new SubmissionBox());
        }
        $taskInstance = new Task\Instance();
        $taskInstance
            ->setUnitInstance($unit)
            ->setTask($task)
            ->setOwner($owner);
        $this->getJoinMapper('task')->save($taskInstance);
    }

    /**
     * For particular folders, find all the unit instances which have
     * open dates occurring between $from and $to
     *
     * @param array               $folderIds An array of folderIds
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @deprecated As of 2016/07/08 this appears to not be used anywhere. Callers now use 'findForFolderDueBetween()'
     * @return Collection
     */
    public function findForFolderOpeningBetween($folderIds, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        $query = "
            SELECT {$this->getColumnString('cui')}
            FROM {$this->getTable()} cui
            WHERE category_id IN (:folderIds)
            AND open_date BETWEEN :from AND :to
            AND deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'folderIds' => implode(',', $folderIds),
            'from' => $from->getDatabase(),
            'to' => $to->getDatabase()
        ]);

        return $this->hydrateList($statement, 'cui');
    }

    /**
     * For particular folders, find all the unit instances which have
     * open dates occurring between $from and $to
     *
     * @param array               $folderIds An array of folderIds
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return Collection
     */
    public function findForFolderDueBetween($folderIds, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        // Allow null dates in from/to
        $dateFilter = '';
        $params = Builder::getArrayParameters($folderIds, 'folderId');
        $arrayPlaceholders = Builder::getArrayPlaceholders($folderIds, 'folderId');

        if ($from instanceof Alaress_Date_Format && !$from->isNull()) {
            $dateFilter      = $dateFilter . ' AND cui.due_date >= :from';
            $params['from']  = $from->getDatabaseDate();
        }
        if ($to instanceof Alaress_Date_Format && !$to->isNull()) {
            $dateFilter    = $dateFilter . ' AND cui.due_date <= :to';
            $params['to']  = $to->getDatabaseDate();
        }

        $query = "
            SELECT {$this->getColumnString('cui')}
            FROM {$this->getTable()} cui
            WHERE category_id IN {$arrayPlaceholders}
            $dateFilter
            AND deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        return $this->hydrateList($statement, 'cui');
    }

    /**
     * @param UnitInstance[]|Collection $unit_instance_list
     *
     * @return UnitInstance[]|Collection
     */
    protected function augment($unit_instance_list)
    {
        // Load tasks into each unit
        /** @var Task\Instance[]|Collection $task_list */
        $task_list = $this->findJoinedModelList('task', $unit_instance_list, ['order' => ['order' => 'asc',],]);

        // Load completions for each unit, and load into task list
        /** @var Task\Completion[]|Collection $completion_list */
        $completion_list = $this->getJoinMapper('task')
            ->getJoinMapper('completion')
            ->findLatestForList($task_list->getColumnValues('id'));

        $grouped_completion_list = $completion_list->groupByCallback(
            function ($item) {
                /** @var Task\Completion $item */
                return $item->getTask()->getId();
            }
        );
        foreach ($task_list as $task) {
            $grouped_list = $grouped_completion_list->getOffset($task->getId());
            if ($grouped_list instanceof Collection) {
                $task->setCompletionList($grouped_list);
            }
        }

        // Load task list for all units
        $grouped_task_list = $task_list->groupByCallback(
            function ($item) {
                /** @var Task\Instance $item */
                return $item->getUnitInstance()->getId();
            }
        );
        foreach ($unit_instance_list as $unit_instance) {
            $task_list = $grouped_task_list->getOffset($unit_instance->getId());
            if ($task_list instanceof Collection) {
                $unit_instance->setTasks($task_list);
            }
        }

        return $unit_instance_list;
    }
}
