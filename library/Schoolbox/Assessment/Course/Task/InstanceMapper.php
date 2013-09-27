<?php
/**
 * Mapper for the task instance model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

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
        $this->registerCallback('preSave',  array($this, 'preSave'));
        $this->registerCallback('postSave', array($this, 'postSave'));
    }

    /**
     * Pre-save callback - copies details from the task object into this object.
     *
     * @param  Instance $object
     * @return bool     Always true
     */
    public function preSave(Instance $object) {
        if (!$object->getId()) {
            $task = $object->getTask();
            $object
                ->setTitle($task->getTitle())
                ->setLink($task->getLink())
                ->setOrder($task->getOrder())
                ->setRequiresConfirmation($task->getRequiresConfirmation())
            ;
        }
        return true;
    }

    /**
     * Post-save callback - if this is a new item, copies the submission box or
     * assessment that is a part of the task.
     *
     * @param  Instance $object
     * @param  bool     $is_new
     * @return void
     */
    public function postSave(Instance $object, $is_new = false) {
        if ($is_new) {
            $task   = $object->getTask();
            $cloner = new Cloner($object);
            if (($assess_id = $task->getAssessment()->getId())) {
                $assessment = $cloner->cloneAssessment();
                $this->updateAll($object, 'assess_id', $assessment->getId());
            }
            if (($box_id = $task->getSubmissionBox()->getId())) {
                $box = $cloner->cloneSubmissionBox();
                $this->updateAll($object, 'box_id', $box->getId());
            }
        }
    }
}
