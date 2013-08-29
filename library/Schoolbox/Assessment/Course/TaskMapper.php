<?php
/**
 * Mapper for the Task model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Category;

class TaskMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('task');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\Task');
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
        $this->hasMap(array(
            'column'     => 'assess_id',
            'property'   => 'assessment',
            'type'       => self::TYPE_MODEL,
            'join'       => 'assessment',
        ));
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
            'mapper'    => 'Schoolbox\\Resource\\CategoryMapper',
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

        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerCallback('preSave', array($this, 'preSave'));
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
            throw new Mapper\Exception('Instance of object not found');
        }
    }

    /**
     * Gets the tasks in the given category.
     *
     * @param int|Category $category Either a Category object or a category ID
     * @param bool         $deleted  True to get deleted tasks too
     *
     * @return Collection
     */
    public function findByCategory($category, $deleted = false) {
        $query = $this->getBaseQuery() . " WHERE t.category_id=?";
        if (!$deleted) {
            $query .= ' AND '.$this->getSoftDeleteFilterSql('t');
        }
        $query .= ' ORDER BY t.order ASC, t.title ASC';

        $statement = $this->database->prepare($query);
        $cat_id    = ($category instanceof Category) ? $category->getId() : $category;
        $statement->execute(array($cat_id));
        return $this->hydrateList($statement, 't', array($this, 'baseHydrateCallback'));
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
            $statement->bindValue('cat_id', $object->getCategory()->getId(), \PDO::PARAM_INT);
            $statement->execute();
            $object->setOrder(intval($statement->fetchColumn())+1);
        }
        return true;
    }
}
