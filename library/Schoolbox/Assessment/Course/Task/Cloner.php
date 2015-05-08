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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

// These are used for older assess export related code:
// while this is handler-related, it works.
use Assess_Main;
use Assess_Factory;
use Assess_Question;
use Assess_Section;
use Assess_Export_Xml;
use Assess_Import_Xml;

class Cloner
{
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
    public function __construct(Instance $object)
    {
        $this->task_instance = $object;
        $this->unit          = UnitInstanceMapper::getInstance()->findById(
            $object->getUnitInstance()->getId()
        );
        $component = \Component_InstanceMapper::getInstance()->findById(
            $this->unit->getComponent()->getId()
        );
        $this->category = CategoryMapper::getInstance()->findById($component->getIdentifier());
    }

    /**
     * Clones the assessment (if there is one) that is attached to this object's
     * task instance.
     *
     * @return OnlineAssessment The assessment that was created.
     * @throws ClonerException  if the assessment was not able to be created
     */
    public function cloneAssessment()
    {
        $assess_id = $this->task_instance->getTask()->getAssessment()->getId();
        if ($assess_id) {
            $mapper   = OnlineAssessmentMapper::getInstance();
            $handler  = new Assess_Main();
            $section  = new Assess_Section();
            $factory  = new Assess_Factory();
            $question = new Assess_Question();
            $assess   = $handler->getByID($assess_id);
            if (empty($assess) || $assess['deleted_at']) {
                throw new ClonerException('No assessment is attached to this task');
            }

            // Export, then import
            $export = new Assess_Export_Xml(
                $assess,
                $factory,
                $section,
                $question,
                new \SitePage(false)
            );
            $output = $export->retrieveContents();

            $import = new Assess_Import_Xml(
                $this->category->getId(),
                $handler,
                $section,
                $factory,
                $output,
                new \SitePage(false)
            );
            $newAssessId = $import->import();

            // Get curriculum codes out of old assess
            // (yes, I currently need both the mapper and the handler version!)
            /** @var OnlineAssessment $oldAssess */
            $oldAssess = $mapper->findById($assess_id);
            // And update the mapper version
            /** @var OnlineAssessment $newAssess */
            $newAssess = $mapper->findById($newAssessId);
            $newAssess
                ->setTask(null)
                ->setTaskInstance($this->task_instance)
                ->setUser($_SESSION['id'])
                ->setStart($this->getOpenTimeFromTask())
                ->setFinish($this->getAssessmentCloseTimeFromTask())
                ->setCurriculumNodes($oldAssess->getCurriculumNodes())
            ;
            $mapper->save($newAssess);

            return $newAssess;
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
    public function cloneSubmissionBox()
    {
        $box_id = $this->task_instance->getTask()->getSubmissionBox()->getId();
        if ($box_id) {
            try {
                $box = SubmissionBoxMapper::getInstance()->findById($box_id);
            } catch (MapperException $e) {
                throw new ClonerException('No submission box is attached to this task');
            }
            $box
                ->setTask(null)
                ->setTaskInstance($this->task_instance)
                ->setOwner($_SESSION['id'])
                ->setCategory($this->category)
                ->setPublishDate($this->getOpenTimeFromTask())
                ->setOpenDate($this->getOpenTimeFromTask())
                ->setDueDate($this->getSubmissionBoxCloseTimeFromTask())
                ->setLateDate($this->getSubmissionBoxCloseTimeFromTask())
            ;
            SubmissionBoxMapper::getInstance()->create($box);

            // Clone rubrix too
            SubmissionBox\Rubric\UsageMapper::getInstance()
                ->copyFromCopiedSubmissionBox($box_id, $box->getId());
            SubmissionBox\Rubric\DescriptorMapper::getInstance()
                ->copyFromCopiedSubmissionBox($box_id, $box->getId());

            return $box;
        }
        throw new ClonerException('No submission box is attached to this task');
    }

    /**
     * Updates linked items (submission box or online assessment) hat is
     * attached to this object's task instance.
     *
     * @return void
     */
    public function updateLinkedItems()
    {
        $this->updateTaskOpenTime();
        $this->updateTaskCloseTime();
    }

    /**
     * Updates this task's open time.
     *
     * @return $this
     */
    public function updateTaskOpenTime()
    {
        try {
            $assess_id = $this->task_instance->getAssessment()->getId();
            if ($assess_id) {
                /** @var OnlineAssessment $assess_object */
                $assess_object = OnlineAssessmentMapper::getInstance()->findById($assess_id);
                $assess_object->setStart($this->getOpenTimeFromTask());
                OnlineAssessmentMapper::getInstance()->save($assess_object);
            }
        } catch (Exception $e) {
            // quiz may have been deleted. Nothing to see here, move along
        }
        try {
            $box_id = $this->task_instance->getSubmissionBox()->getId();
            if ($box_id) {
                /** @var SubmissionBox $box */
                $box = SubmissionBoxMapper::getInstance()->findById($box_id);
                $box
                    ->setPublishDate($this->getOpenTimeFromTask())
                    ->setOpenDate($this->getOpenTimeFromTask())
                ;
                SubmissionBoxMapper::getInstance()->save($box);
            }
        } catch (Exception $e) {
            // box may have been deleted. Nothing to see here, move along
        }
        return $this;
    }


    /**
     * Updates this task's close time.
     *
     * @return $this
     */
    public function updateTaskCloseTime()
    {
        try {
            $assess_id = $this->task_instance->getAssessment()->getId();
            if ($assess_id) {
                /** @var OnlineAssessment $assess_object */
                $closeTime = $this->getAssessmentCloseTimeFromTask();
                $assess_object = OnlineAssessmentMapper::getInstance()->findById($assess_id);
                // If this item's close time is the same as the task's close time,
                // update it
                $assess_object->setFinish($closeTime);
                OnlineAssessmentMapper::getInstance()->save($assess_object);
            }
        } catch (Exception $e) {
            // quiz may have been deleted. Nothing to see here, move along
        }
        try {
            $box_id = $this->task_instance->getSubmissionBox()->getId();
            if ($box_id) {
                /** @var SubmissionBox $box */
                $box = SubmissionBoxMapper::getInstance()->findById($box_id);
                $box
                    ->setDueDate($this->getSubmissionBoxCloseTimeFromTask())
                    ->setLateDate($this->getSubmissionBoxCloseTimeFromTask())
                ;
                SubmissionBoxMapper::getInstance()->save($box);
            }
        } catch (Exception $e) {
            // box may have been deleted. Nothing to see here, move along
        }
        return $this;
    }

    /**
     * Gets the time that all boxes and assessments from this task should open.
     * Currently, they open at 12am on the open date of the task.
     *
     * @return \Alaress_Date_Format
     */
    public function getOpenTimeFromTask()
    {
        $openDate = $this->unit->getOpenDate();
        return $openDate->startOfDay();
    }

    /**
     * Gets the time that all boxes and assessments from the task should close.
     * Currently, they close at 12am the day after the close date, so that the
     * assessment is open for the whole day.
     *
     * @return Alaress_Date_Format
     */
    public function getSubmissionBoxCloseTimeFromTask()
    {
        $closeDate = $this->unit->getDueDate();
        return $closeDate->endOfDay();
    }

    /**
     * Gets the time that all boxes and assessments from the task should close.
     * Currently, they close at 12am the day after the close date, so that the
     * assessment is open for the whole day.
     *
     * @return Alaress_Date_Format
     */
    public function getAssessmentCloseTimeFromTask()
    {
        $closeDate = $this->unit->getDueDate();
        return $closeDate->endOfDay();
    }
}
