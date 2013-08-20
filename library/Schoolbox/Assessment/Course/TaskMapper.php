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
use Schoolbox\User;
use Schoolbox\Resource\Category;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;

class TaskMapper extends Schoolbox\Mapper {

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
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox',
            'this_key'  => 'type',
            'other_key' => 'id',
        ));
        $this->hasJoin('assessment', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Component\\OnlineAssessment',
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * @throws Schoolbox\Mapper\Exception if task is not found
     * @param  int  $id
     * @return Task
     */
    public function findById($id) {
        $cat_mapper    = $this->getJoinMapper('category');
        $box_mapper    = $this->getJoinMapper('submission_box');
        $assess_mapper = $this->getJoinMapper('assessment');
        $owner_mapper  = $this->getJoinMapper('owner');

        //
        // Join: category, submission box, assessment, owner.
        $query = "
            SELECT
                {$this->getColumnString('t')},
                {$cat_mapper->getColumnString('c')},
                {$box_mapper->getColumnString('sb')},
                {$assess_mapper->getColumnString('as')},
                {$owner_mapper->getColumnString('u')}
            FROM {$this->getTable()} AS t
            LEFT JOIN {$this->getJoinSql('category', 't', 'c')}
            LEFT JOIN {$this->getJoinSql('submission_box', 't', 'sb')}
            LEFT JOIN {$this->getJoinSql('assessment', 't', 'as')}
            LEFT JOIN {$this->getJoinSql('owner', 't', 'u')}
            WHERE sb.id=?
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        if (($row = $statement->fetch()) !== false) {
            /** @var Task $task */
            $task = $this->hydrate($row, 't');
            $task
                ->setCategory($cat_mapper->hydrate($row, 'c'))
                ->setOwner($owner_mapper->hydrate($row, 'u'))
                ->setAssessment($assess_mapper->hydrate($row, 'as'))
                ->setSubmissionBox($box_mapper->hydrate($row, 'sb'))
            ;
            $this->throwIfDeleted($task);
            return $task;
        }
        else {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }
    }

    // public function findForCategory(Category $category)
}
