<?php
/**
 * Mapper for the task instance model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Mapper;

class InstanceMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('task_instance');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\Task\\Instance');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'task_id',
            'property'   => 'task',
            'type'       => self::TYPE_MODEL,
            'join'       => 'task',
        ));
        $this->hasMap(array(
            'column'     => 'course_unit_instance_id',
            'property'   => 'unitInstance',
            'type'       => self::TYPE_MODEL,
            'join'       => 'course_unit_instance',
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

        $this->hasJoin('task', array(
            'mapper'    => 'Schoolbox\\Assessment\\Course\\TaskMapper',
            'this_key'  => 'task_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('course_unit_instance', array(
            'mapper'    => 'Schoolbox\\Assessment\\Course\\UnitInstanceMapper',
            'this_key'  => 'course_unit_instance_id',
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
        $this->hasJoin('completion', array(
            'mapper'    => '\\Schoolbox\\Assessment\\Course\\Task\\InstanceCompletionMapper',
            'this_key'  => 'id',
            'other_key' => 'task_instance_id',
            'multiple'  => true,
        ));

        $this->hasSoftDeleteDateField('deleted_at');
        $this->registerCallback('preSave',        array($this, 'preSave'));
        $this->registerCallback('postSave',       array($this, 'postSave'));
        $this->registerCallback('postDelete',     array($this, 'postDelete'));
        $this->registerCallback('postSoftDelete', array($this, 'postDelete'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'postRestore'));
    }

    /**
     * Pre-save callback.
     * * copies details from the task object into this object.
     * * If order isn't set, then sets it at the end of this task.
     *
     * @param  Instance $object
     * @return bool     Always true
     */
    public function preSave(Instance $object) {
        if (!$object->getId()) {
            $task = $object->getTask();
            if ($task instanceof Task && $task->getId()) {
                $object
                    ->setTitle($task->getTitle())
                    ->setLink($task->getLink())
                    ->setOrder($task->getOrder())
                    ->setRequiresConfirmation($task->getRequiresConfirmation())
                ;
            }
        }
        $order = $object->getOrder();
        if (!isset($order)) {
            $statement = $this->database->prepare(
                "SELECT COALESCE(MAX(`order`), -1)
                FROM `{$this->getTable()}` WHERE `course_unit_instance_id` = :unit_id"
            );
            $unit    = $object->getUnitInstance();
            $unit_id = ($unit instanceof UnitInstance) ? $unit->getId() : $unit;
            $statement->bindValue('unit_id', $unit_id, \PDO::PARAM_INT);
            $statement->execute();
            $object->setOrder(intval($statement->fetchColumn())+1);
        }
        return true;
    }

    /**
     * Post-save callback - if this is a new item, copies the submission box or
     * assessment that is a part of the task.
     *
     * @param  Instance $object
     * @param  Instance $previous
     * @return void
     */
    public function postSave(Instance $object, $previous = null) {
        if (!$previous) {
            $task = $object->getTask();
            if ($task instanceof Task && $task->getId()) {
                $cloner = new Cloner($object);
                try {
                    if (($assess_id = $task->getAssessment()->getId())) {
                        $assessment = $cloner->cloneAssessment();
                        $this->updateAll($object, 'assess_id', $assessment->getId());
                    }
                    if (($box_id = $task->getSubmissionBox()->getId())) {
                        $box = $cloner->cloneSubmissionBox();
                        $this->updateAll($object, 'box_id', $box->getId());
                    }
                } catch (ClonerException $e) {
                    // something went wrong in the clone process.
                    // Leave this task alone.
                    // (it probably shouldn't have been imported at all.)
                }
            }
        }
    }

    /**
     * Post-delete callback - deletes related assessment and course.
     *
     * @param Instance $object
     */
    public function postDelete(Instance $object) {
        if ($object->getAssessment()->getId()) {
            OnlineAssessmentMapper::getInstance()->delete($object->getAssessment());
        }
        if ($object->getSubmissionBox()->getId()) {
            SubmissionBoxMapper::getInstance()->delete($object->getSubmissionBox());
        }
    }

    /**
     * Post-restore callback - undeletes related assessment and course.
     *
     * @param Instance $object
     */
    public function postRestore(Instance $object) {
        if ($object->getAssessment()->getId()) {
            OnlineAssessmentMapper::getInstance()->restoreSoftDeleted($object->getAssessment());
        }
        if ($object->getSubmissionBox()->getId()) {
            SubmissionBoxMapper::getInstance()->restoreSoftDeleted($object->getSubmissionBox());
        }
    }


    /**
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        // vicious lies
        return true;
    }

}
