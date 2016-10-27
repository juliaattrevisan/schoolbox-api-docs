<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Component_Instance;
use Schoolbox\Assessment\Course;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;
use Alaress_Date_Format;

/**
 * Mapper for the unit instance model.
 *
 * @deprecated Use \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper
 *             and its related business domain classes instead.
 */
class UnitInstanceMapper extends BaseMapper {

    protected function mapping() {
        $this->hasTable('course_unit_implementation');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\UnitInstance');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        // Category: the category that the component is on
        $this->hasMap(array(
            'column'     => 'category_id',
            'property'   => 'category',
            'type'       => self::TYPE_MODEL,
            'join'       => 'category',
            'join_def'   => array(
                'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
                'this_key'  => 'category_id',
                'other_key' => 'id',
            ),
        ));
        // Unit category: the category that this unit was created from, not the
        // one that the component is on
        $this->hasMap(array(
            'column'     => 'unit_category_id',
            'property'   => 'unitCategory',
            'type'       => self::TYPE_MODEL,
            'join'       => 'unit_category',
        ));
        // Component: the component that this unit is displayed on
        $this->hasMap(array(
            'column'     => 'component_id',
            'property'   => 'component',
            'type'       => self::TYPE_MODEL,
            'join'       => 'component',
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

        $this->hasJoin('unit_category', array(
            'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
            'this_key'  => 'unit_category_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('course_folder', array(
            'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
            'this_key'  => 'parent',
            'other_key' => 'id',
        ));
        $this->hasJoin('component', array(
            'mapper'    => 'Component_InstanceMapper',
            'this_key'  => 'component_id',
            'other_key' => 'id',
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
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'postRestore'));
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
     * Find the componentId used for a particular Course
     * (a set of Units...)
     *
     * Also determine whether Courses are restorable. These
     * are in a list of originating Course Folders whose Units
     * had their Course Outlines deleted from $folderId
     *
     * Returns you an associative array between a folderId and
     * the componentId which used it along with the restorable
     * state of the Course
     *
     * @param $folderId
     * @return array
     */
    public function findComponentsForCourses($folderId)
    {
        $allCourses = [];
        $allRestorableUnitIds = [];

        $allUnits = $this->findByColumn('category_id', $folderId);
        $allRestorableUnits = $this->findRestorableUnits($folderId);

        /* Just get the ids, for some easy checking
         */
        foreach ($allRestorableUnits as $currentRestorableUnit) {
            $allRestorableUnitIds[] = $currentRestorableUnit->getId();
        }

        /* Check every unit -_-
         */
        foreach ($allUnits as $currentUnit) {
            try {
                $course = $this->findCourseForUnit($currentUnit);
                /* component_id is the closest concept to a course_id
                 * and we can derive it from the associated unit
                 */
                $allCourses[$course->getId()] = [
                    'componentId' => $currentUnit->getComponent()->getId(),
                    'restorable' => in_array($currentUnit->getId(), $allRestorableUnitIds) ? true : false,
                ];
            } catch (BaseMapper\Exception $e) {
                // what can I do, except:
                continue;
            }
        }

        return $allCourses;
    }

    /**
     * Retrieves unit instances for the given component.
     *
     * @param  Component_Instance        $component
     * @return UnitInstance[]|Collection
     */
    public function findForComponent(Component_Instance $component) {
        /** @var Collection|UnitInstance[] $unit_list */
        $unit_list = $this->findByColumn(
            'component_id',
            $component->getId(),
            array(
                'order' => array(
                    'open_date' => 'asc',
                    'due_date'  => 'asc',
                    'title'     => 'asc',
                )
            )
        );

        // Natural sort
        $unit_list->sortByCallback(
            function($a, $b) {
                /** @var UnitInstance $a */
                /** @var UnitInstance $b */
                // Open date, then due date, then title
                $cmp = $a->getOpenDate()->compare($b->getOpenDate());
                if ($cmp) {
                    return $cmp;
                }
                $cmp = $a->getDueDate()->compare($b->getDueDate());
                if ($cmp) {
                    return $cmp;
                }
                return strnatcasecmp($a->getTitle(), $b->getTitle());
            }
        );

        // Hydrate the list of tasks within each unit, sorting by order.
        $task_list = $this->getJoinMapper('task')->findByColumn(
            'course_unit_instance_id',
            $unit_list->getColumnValues('id'),
            array(
                'order' => array(
                    'order' => 'asc',
                )
            )
        );

        // Add only the relevant tasks to each unit.
        foreach ($unit_list as $unit) {
            /** @var \CallbackFilterIterator|Task\Instance[] $task_iterator */
            $task_iterator = new \CallbackFilterIterator(
                $task_list->getIterator(),
                function ($item) use ($unit) {
                    /** @var Task\Instance $item */
                    return $item->getUnitInstance()->getId() == $unit->getId();
                }
            );
            foreach ($task_iterator as $task) {
                // Load the list of related due work and quiz items too.
                try {
                    if (($boxId = $task->getSubmissionBox()->getId())) {
                        $task->setSubmissionBox(
                            $this->getJoinMapper('task.submission_box')->findById($boxId)
                        );
                    }
                    if (($quizId = $task->getAssessment()->getId())) {
                        $task->setAssessment(
                            $this->getJoinMapper('task.assessment')->findById($quizId)
                        );
                    }
                } catch (BaseMapper\Exception $e) {
                    // Object was deleted or something: noop
                }
                $unit->getTasks()->add($task);
            }
        }
        return $unit_list;
    }

    /**
     * Gets all units that belong to deleted components for the given category.
     *
     * @param  int $categoryId
     * @return UnitInstance[]|Collection
     */
    public function findOrphanedForCategoryId($categoryId) {
        $query = "
            SELECT {$this->getColumnString('ui')}
            FROM {$this->getTable()} `ui`
            LEFT JOIN {$this->getJoinSql('component', 'ui', 'co')}
            WHERE `ui`.`category_id` = :category
            AND `co`.`identifier` IS NULL
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(array('category' => $categoryId));
        return $this->hydrateList($statement, 'ui');
    }

    /**
     * Gets all units and tasks for the given category.
     *
     * @param  int $category_id The category to get units for
     * @param  int $courseId    If provided, restrict results to this course
     * @return UnitInstance[]|Collection
     */
    public function findForCategoryId($category_id, $courseId = null)
    {
        /** @var UnitInstance[]|Collection $unit_instance_list */
        if ($courseId) {
            $query = "
                SELECT {$this->getColumnString('ui')}
                FROM {$this->getTable()} `ui`
                INNER JOIN {$this->getJoinSql('unit_category', 'ui', 'uc')}
                WHERE `ui`.`category_id` = :category
                AND `uc`.`parent` = :course
            ";
            $statement = $this->getDatabase()->prepare($query);
            $statement->execute(['category' => $category_id, 'course' => $courseId]);
            $unit_instance_list = $this->hydrateList($statement, 'ui');
        } else {
            $unit_instance_list = $this->findByColumnList(
                ['category_id' => $category_id,],
                array(
                    'order' => array('open_date' => 'desc')
                )
            );
        }
        return $this->augment($unit_instance_list);
    }


    /**
     * Retrieves a unit instance by ID and category ID.
     *
     * Implemented as a function because it's common to be provided with a unit
     * instance ID, and a category ID for checking it against.
     *
     * @throws BaseMapper\Exception if no instance of model is found
     * @throws BaseMapper\Exception\Deleted if the model was found but has been deleted
     * @param  int $id
     * @param  int $category_id
     * @return UnitInstance
     */
    public function findByIdAndCategoryId($id, $category_id) {
        $query = "
            SELECT
                {$this->getColumnString('ui')},
                {$this->getJoinColumnString('component', 'ci')}
            FROM `{$this->getTable()}` `ui`
            LEFT JOIN {$this->getJoinSql('component', 'ui', 'ci')}
            WHERE `ui`.`id` = :id
            AND `ci`.`identifier` = :category_id
            AND `ci`.`module` = 'homepage'
            LIMIT 1
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array('id' => $id, 'category_id' => $category_id));
        $result = $this->hydrateList(
            $statement, 'ui',
            function($mapper, $model, $row) {
                /**
                 * @var UnitInstanceMapper $mapper
                 * @var UnitInstance       $model
                 */
                $model->setComponent($mapper->getJoinMapper('component')->hydrate($row, 'ci'));
                return $model;
            }
        );
        foreach ($result as $model) {
            $this->throwIfDeleted($model);
            return $model;
        }
        throw new BaseMapper\Exception('Instance of object not found');
    }

    /**
     * Finds all unit instances that are based off a category.
     *
     * @param  int  $categoryId
     * @param  bool $excludeClosed If true, don't include unit instances whose
     *                             due date has passed.
     * @return Collection|UnitInstance[]
     */
    public function findByUnitCategoryId($categoryId, $excludeClosed = true) {
        $query = "
            SELECT
                {$this->getColumnString('ui')},
                {$this->getJoinColumnString('category', 'uc')},
                {$this->getJoinColumnString('component', 'ci')}
            FROM `{$this->getTable()}` `ui`
            INNER JOIN {$this->getJoinSql('category', 'ui', 'uc')}
            INNER JOIN {$this->getJoinSql('component', 'ui', 'ci')}
            WHERE `ui`.`unit_category_id` = :categoryId
            AND `ci`.`module` = 'homepage'
            AND `ui`.`deleted_at` IS NULL
        ";
        if ($excludeClosed) {
            $query .= ' AND `ui`.`due_date` >= CURDATE()';
        }
        $query .= ' ORDER BY `ui`.`open_date` ASC, `ui`.`due_date` ASC';
        $statement = $this->database->prepare($query);
        $statement->bindValue('categoryId', $categoryId);
        $statement->execute();
        return $this->hydrateList(
            $statement, 'ui',
            function($mapper, $model, $row) {
                /**
                 * @var UnitInstanceMapper $mapper
                 * @var UnitInstance       $model
                 */
                $model->setCategory($mapper->getJoinMapper('category')->hydrate($row, 'uc'));
                $model->setComponent($mapper->getJoinMapper('component')->hydrate($row, 'ci'));
                return $model;
            }
        );
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
     * Post-restore callback - restores all task instances that are related to
     * this item.
     *
     * @param UnitInstance $object
     */
    public function postRestore(UnitInstance $object) {
        $task_instance_mapper = Task\InstanceMapper::getInstance();
        $task_instance_mapper->restoreSoftDeleted(
            $task_instance_mapper->findByColumn(
                'course_unit_instance_id',
                $object->getId(),
                ['deleted' => true,]
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
        if (!($task->getAssessment() instanceof Model)) {
            $task->setAssessment(new Model());
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
        $params = BaseMapper\PredicateBuilder::getArrayParameters($folderIds, 'folderId');
        $arrayPlaceholders = BaseMapper\PredicateBuilder::getArrayPlaceholders($folderIds, 'folderId');

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
