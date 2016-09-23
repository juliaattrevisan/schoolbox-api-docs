<?php
/**
 * Mapper for the Task model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox;
//use Schoolbox\Assessment\Mapper as AssessmentMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Task mapper
 *
 * @method Task createModel($id=null)
 */
class TaskMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('task');
        $this->hasModel(Task::class);
        $this->hasMap(array(
            'column'     => 'id',
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
            'column'     => 'title',
            'property'   => 'title',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'link',
            'property'   => 'link',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'requires_confirmation',
            'property'   => 'requiresConfirmation',
            'type'       => self::TYPE_BOOLEAN,
        ));
        $this->hasMap(array(
            'column'     => 'box_id',
            'property'   => 'submissionBox',
            'type'       => self::TYPE_MODEL,
            'join'       => 'submission_box',
        ));


        // Please forgive my historical naming choices here!
        // assess_id:     join from this task onto a quiz (will be removed, but needs to be kept for import purposes)
        // assessment_id: join from this task onto the assessment it belongs to
        $this->hasMap(array(
            'column'     => 'assess_id',
            'property'   => 'assessment',
            'type'       => self::TYPE_MODEL,
            'join'       => 'assessment',
        ));
//        $this->joins('assessment_id', AssessmentMapper::class, null, null, null, 'assessment', 'assessment');


        $this->hasMap(array(
            'column'     => 'user_id',
            'property'   => 'owner',
            'type'       => self::TYPE_MODEL,
            'join'       => 'owner',
        ));
        $this->hasMap(array(
            'column'     => 'order',
            'property'   => 'order',
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
            'mapper'    => 'Schoolbox\\Resource\\Folder\\Mapper',
            'this_key'  => 'category_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('owner', array(
            'mapper'    => 'Schoolbox\\UserMapper',
            'this_key'  => 'user_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('submission_box', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
            'this_key'  => 'box_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('assessment', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\OnlineAssessmentMapper',
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));

        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deleted');
        $this->registerCallback('preSave', array($this, 'preSave'));
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'hardDeleteRelatedObjects'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteRelatedObjects'));
    }

    /**
     * Helper method for retrieving a sensible base task. It contains a join to
     * the category, submission box, assessment and owner of this task.
     *
     * @return string
     */
    protected function getBaseQuery() {
        return "
            SELECT
                {$this->getColumnString('t')},
                {$this->getJoinColumnString('category',       'c')},
                {$this->getJoinColumnString('submission_box', 'sb')},
                {$this->getJoinColumnString('assessment',     'as')},
                {$this->getJoinColumnString('owner',          'u')}
            FROM {$this->getTable()} AS t
            LEFT JOIN {$this->getJoinSql('category',       't', 'c')}
            LEFT JOIN {$this->getJoinSql('submission_box', 't', 'sb')}
            LEFT JOIN {$this->getJoinSql('assessment',     't', 'as')}
            LEFT JOIN {$this->getJoinSql('owner',          't', 'u')}
        ";
    }

    /**
     * Hydrate callback for use with queries created with $this->getBaseQuery();
     *
     * @param TaskMapper $mapper
     * @param Task       $model
     * @param array      $row
     *
     * @return Task
     */
    public static function baseHydrateCallback($mapper, $model, $row) {
        $model
            ->setCategory($mapper->hydrateJoin($row, 'category', 'c'))
            ->setOwner($mapper->hydrateJoin($row, 'owner', 'u'))
            ->setAssessment($mapper->hydrateJoin($row, 'assessment', 'as'))
            ->setSubmissionBox($mapper->hydrateJoin($row, 'submission_box', 'sb'))
        ;
        return $model;
    }

    /**
     * {@inheritdoc}
     *
     * @return Task
     */
    public function findById($id) {
        $query     = $this->getBaseQuery() . " WHERE t.id=?";
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));
        if (($row = $statement->fetch()) !== false) {
            $task = $this->hydrate($row, 't', array($this, 'baseHydrateCallback'));
            $this->throwIfDeleted($task);
            return $task;
        }
        else {
            throw new MapperException('Instance of object not found');
        }
    }

    /**
     * Gets the tasks in the given category.
     *
     * @param int|Folder   $category Either a Category object or a category ID
     * @param bool         $deleted  True to get deleted tasks too
     *
     * @return Collection|Task[]
     */
    public function findByCategory($category, $deleted = false) {
        $query = $this->getBaseQuery() . " WHERE t.category_id=?";
        if (!$deleted) {
            $query .= ' AND '.$this->getSoftDeleteFilterSql('t');
        }
        $query .= ' ORDER BY t.order ASC, t.title ASC';

        $statement = $this->database->prepare($query);
        $cat_id    = ($category instanceof Folder) ? $category->getId() : $category;
        $statement->execute(array($cat_id));
        return $this->hydrateList($statement, 't', array($this, 'baseHydrateCallback'));
    }

    /**
     * Finds a list of tasks for a list of categories, then groups them by their
     * category.
     *
     * @param  int[] $category_id_list A list of category IDs to retrieve tasks for
     * @return Collection|Collection[]
     */
    public function findGroupedListForCategoryList($category_id_list) {
        $task_list = TaskMapper::getInstance()->findByColumn(
            'category_id',
            $category_id_list,
            array('order' => array('category_id' => 'asc'),)
        );
        return $task_list->groupByCallback(
            function($item) {
                /** @var Task $item */
                return $item->getCategory()->getId();
            }
        );
    }

    /**
     * Find the originally-specified list of tasks for a given
     * Unit Category ID (the originating unit for a unit instance)
     *
     * @param $unitCategoryId
     * @return \Schoolbox\Collection
     */
    public function findAbstractTasksForUnit($unitCategoryId)
    {
        $query = "
            SELECT {$this->getColumnString('t')}
            FROM {$this->getTable()} t
            WHERE t.category_id = :unitCategoryId
            AND t.deleted_at IS NULL;
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'unitCategoryId' => $unitCategoryId
        ]);

        return $this->hydrateList($statement, 't');
    }

    /**
     * Pre-save method: if this doesn't have an order, sets it to whatever the
     * highest order for this category is, +1 (so it shows as the last item in
     * the list)
     *
     * @param  Task $object
     * @return bool Always true
     */
    public function preSave(Task $object) {
        $order = $object->getOrder();
        if (!isset($order)) {
            $statement = $this->database->prepare(
                "SELECT COALESCE(MAX(`order`), -1) FROM `{$this->getTable()}` WHERE `category_id` = :cat_id"
            );
            // Ugly code for dealing with the possibility that someone set a
            // scalar category instead of an object.
            // TODO consider standardising object values before callbacks are called
            $category = $object->getCategory();
            $cat_id   = ($category instanceof Folder) ? $category->getId() : $category;
            $statement->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
            $statement->execute();
            $object->setOrder(intval($statement->fetchColumn())+1);
        }
        return true;
    }

    /**
     * Post-delete method - cascades soft delete of submission box and quiz.
     *
     * @param Task $object
     */
    public function softDeleteRelatedObjects(Task $object)
    {
        if ($object->getAssessment()->getId()) {
            $this->getJoinMapper('assessment')->softDelete($object->getAssessment());
        }
        if ($object->getSubmissionBox()->getId()) {
            $this->getJoinMapper('submission_box')->softDelete($object->getSubmissionBox());
        }
    }

    /**
     * Post-delete method - cascades hard delete of submission box and quiz.
     *
     * @param Task $object
     */
    public function hardDeleteRelatedObjects(Task $object)
    {
        if ($object->getAssessment()->getId()) {
            $this->getJoinMapper('assessment')->hardDelete($object->getAssessment());
        }
        if ($object->getSubmissionBox()->getId()) {
            $this->getJoinMapper('submission_box')->hardDelete($object->getSubmissionBox());
        }
    }

    /**
     * Count the number of tasks in the given unit
     *
     * @param Folder $unit
     * @return int
     */
    public function countByUnit(Folder $unit)
    {
        return $this->countByColumn('category_id', $unit->getId());
    }


    /**
     * Count the number of tasks in the given units and return as an associative array
     *
     * @param Collection|Folder[] $units
     * @return array
     */
    public function countForEachUnit(Collection $units)
    {
        $unitIds = $units->getColumnValues('id');

        // preset everything to zero: we still want the keys populated even if the query somehow returns nothing
        $taskCounts = array_combine($unitIds, array_fill(0, count($unitIds), 0));

        foreach ($units as $unit) {
            // iterate and find the task count for each unit
            $taskCounts[$unit->getId()] = $this->countByUnit($unit);
        }

        return $taskCounts;
    }

}
