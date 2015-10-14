<?php
/**
 * Mapper for the unit instance model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Component_Instance;
use Component_InstanceMapper;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Course\Task\Cloner;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;

class UnitInstanceMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('course_unit_instance');
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
                'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
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
            'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
            'this_key'  => 'unit_category_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('course_folder', array(
            'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
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
    }

    /**
     * Find the course, whose units from which a Unit
     * $unitInstance was spawned
     *
     * @param UnitInstance $unitInstance
     * @return object
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findCourseForUnit(UnitInstance $unitInstance) {
        $folderMapper = $this->getJoinMapper('course_folder');

        $query = "
            SELECT {$folderMapper->getColumnString('cf')}
            FROM {$this->getTable()} `ui`
            LEFT JOIN {$this->getJoinSql('unit_category', 'ui', 'uc')}
            LEFT JOIN {$this->getJoinSql('course_folder', 'uc', 'cf')}
            WHERE `ui`.`unit_category_id` = :unitId
            GROUP BY `cf`.`id`
            LIMIT 1
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array(
            'unitId' => $unitInstance->getUnitCategory()->getId()
        ));

        $row = $statement->fetch();

        if ($row != false) {
            $folder = $folderMapper->hydrate($row, 'cf');

            return $folder;
        }

        throw new \Schoolbox\Mapper\Exception('Instance of object not found');
    }

    /**
     * Find all the folders which use a Unit that was spawned from
     * the original Course folder, $folder.  This limited to the provided year
     *
     * @param Folder $folder
     * @param $year
     * @return Collection
     */
    public function findAllFoldersUsingCourse(Folder $folder, $year) {
        $folderMapper = $this->getJoinMapper('unit_category');
        $code_mapper = $this->getJoinMapper('category')->getJoinMapper('codes');

        $query = "
            SELECT {$folderMapper->getColumnString('c')}, {$code_mapper->getColumnString('cc')}
            FROM {$this->getTable()} `ui`
            LEFT JOIN {$this->getJoinSql('unit_category', 'ui', 'uc')}
            LEFT JOIN {$this->getJoinSql('course_folder', 'uc', 'cf')}
            LEFT JOIN {$this->getJoinSql('category', 'ui', 'c')}
            LEFT JOIN categoryCode cc ON c.id = cc.categoryID
            WHERE `cf`.`id` = :folderId
              AND c.deleted_at IS NULL
              AND ui.deleted_at IS NULL
              AND uc.deleted_at IS NULL
              AND YEAR(ui.due_date) = :year
            GROUP BY `c`.`id`
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array(
            'folderId' => $folder->getId(),
            'year'     => $year,
        ));

        $collection = new Collection();

        while ($row = $statement->fetch()) {
            $existingCat = $collection->filterBy('id', $row['c.id']);
            if ($existingCat->count()) {
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getCategory()->getId()) {
                    $existingCat->first()->addCode($code);
                }
            }
            else {
                $folder = $folderMapper->hydrate($row, 'c');
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getCategory()->getId()) {
                    $folder->addCode($code);
                }
                $collection->add($folder);
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
                )
            )
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
                } catch (Mapper\Exception $e) {
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
     * @return UnitInstance[]|Collection
     */
    public function findForCategoryId($category_id) {

        /** @var UnitInstance[] $unit_instance_list */
        $unit_instance_list = $this->findByColumn(
            'category_id',
            $category_id,
            array(
                'order' => array('open_date' => 'desc')
            )
        );

        // Load tasks into each unit
        /** @var Task\Instance[]|Collection $task_list */
        $task_list = $this->findJoinedModelList('task', $unit_instance_list, ['order' => ['order' => 'asc',],]);

        // Load completions for each unit, and load into task list
        /** @var Task\InstanceCompletion[]|Collection $completion_list */
        $completion_list = $this->getJoinMapper('task')
            ->getJoinMapper('completion')
            ->findLatestForList($task_list->getColumnValues('id'));

        $grouped_completion_list = $completion_list->groupByCallback(
            function($item) {
                /** @var Task\InstanceCompletion $item */
                return $item->getTaskInstance()->getId();
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
            function($item) {
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


    /**
     * Retrieves a unit instance by ID and category ID.
     *
     * Implemented as a function because it's common to be provided with a unit
     * instance ID, and a category ID for checking it against.
     *
     * @throws Mapper\Exception if no instance of model is found
     * @throws Mapper\Exception\Deleted if the model was found but has been deleted
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
        throw new Mapper\Exception('Instance of object not found');
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
    protected function postSave(UnitInstance $object, $previous = null) {
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
                    ->setOwner($_SESSION['id'])
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
    protected function postDelete(UnitInstance $object) {
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
        if (!($task->getAssessment() instanceof OnlineAssessment)) {
            $task->setAssessment(new OnlineAssessment());
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
}
