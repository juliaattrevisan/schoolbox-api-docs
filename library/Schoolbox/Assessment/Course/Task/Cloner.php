<?php
/**
 * Class for cloning submission boxes and assessments for tasks.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

// These are used for older assess export related code:
// while this is handler-related, it works.
use Assess_Main;
use Assess_Factory;
use Assess_Section;
use Assess_Export_Xml;
use Assess_Import_Xml;

class Cloner {

    /**
     * @var Instance
     */
    protected $task_instance;

    /**
     * @var UnitInstance
     */
    protected $unit;

    /**
     * @var Category
     */
    protected $category;

    /**
     * Creates the cloner, setting up the unit and category objects that are
     * required.
     *
     * @param Instance $object
     */
    public function __construct(Instance $object) {
        $this->task_instance = $object;
        $this->unit          = UnitInstanceMapper::getInstance()->findById(
            $object->getUnitInstance()->getId()
        );
        $this->category = CategoryMapper::getInstance()->findById(
            $object->getUnitInstance()->getComponent()->getIdentifier()
        );
    }

    /**
     * Clones the submission box (if there is one) that is attached to this
     * object's task instance.
     *
     * @return OnlineAssessment The assessment that was created.
     * @throws ClonerException  if the assessment was not able to be created
     */
    public function cloneAssessment() {
        $assess_id = $this->task_instance->getTask()->getAssessment()->getId();
        if ($assess_id) {
            $handler = new Assess_Main();
            $section = new Assess_Section();
            $factory = new Assess_Factory();
            $assess  = $handler->getByID($assess_id);

            // Export, then import
            $export = new Assess_Export_Xml(
                $assess,
                $factory,
                $section,
                new \SitePage()
            );
            $output = $export->retrieveContents();

            $import = new Assess_Import_Xml(
                $this->category->getId(),
                $handler,
                $section,
                $factory,
                $output,
                new \SitePage()
            );
            $assess_id = $import->import();

            // And update the mapper version
            /** @var OnlineAssessment $assess_object */
            $assess_object = OnlineAssessmentMapper::getInstance()->findById($assess_id);
            $assess_object
                ->setTask(null)
                ->setUser($_SESSION['id'])
                ->setStart($this->getOpenTimeFromTask())
                ->setFinish($this->getCloseTimeFromTask())
            ;
            OnlineAssessmentMapper::getInstance()->save($assess_object);
            return $assess_object;
        }
        throw new ClonerException('No assessment is attached to this task');
    }

    /**
     * Clones the submission box (if there is one) that is attached to this
     * object's task instance.
     *
     * @return SubmissionBox   The cloned submission box
     * @throws ClonerException if the box was not able to be created
     */
    public function cloneSubmissionBox() {
        $box_id = $this->task_instance->getTask()->getSubmissionBox()->getId();
        if ($box_id) {
            $box = SubmissionBoxMapper::getInstance()->findById($box_id);
            $box
                ->setTask(null)
                ->setOwner($_SESSION['id'])
                ->setCategory($this->category)
                ->setOpenDate($this->getOpenTimeFromTask())
                ->setDueDate($this->getCloseTimeFromTask())
                ->setLateDate($this->getCloseTimeFromTask())
            ;
            SubmissionBoxMapper::getInstance()->create($box);
            return $box;
        }
        throw new ClonerException('No submission box is attached to this task');
    }

    /**
     * Gets the time that all boxes and assessments from this task should open.
     * Currently, they open at 12am on the open date of the task.
     *
     * @return \Alaress_Date_Format
     */
    protected function getOpenTimeFromTask() {
        return new \Alaress_Date_Format(
            new \DateTime($this->unit->getOpenDate()->getUsingFormat('Y-m-d 00:00:00'))
        );
    }

    /**
     * Gets the time that all boxes and assessments from the task should close.
     * Currently, they close at 12am the day after the close date, so that the
     * assessment is open for the whole day.
     *
     * @return Alaress_Date_Format
     */
    protected function getCloseTimeFromTask() {
        $close_date = new \DateTime($this->unit->getDueDate()->getUsingFormat('Y-m-d 00:00:00'));
        $close_date->modify('+1 day');
        return new \Alaress_Date_Format($close_date);
    }
}