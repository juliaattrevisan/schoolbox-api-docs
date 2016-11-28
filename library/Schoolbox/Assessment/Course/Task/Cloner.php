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
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;


// These are used for older assess export related code:
// while this is handler-related, it works.
use Assess_Main;
use Assess_Factory;
use Assess_Question;
use Assess_Section;
use Assess_Export_Xml;
use Assess_Import_Xml;

/**
 * @deprecated 165
 */
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
     * @var Folder
     */
    protected $category;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

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
        $this->category = FolderMapper::getInstance()->findById($component->getIdentifier());

        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    public function getErrorMapper() {
        return $this->errorMapper;
    }

    /**
     * Clones the assessment (if there is one) that is attached to this object's
     * task instance.
     *
     * @return Quiz The assessment that was created.
     * @throws ClonerException  if the assessment was not able to be created
     */
    public function cloneAssessment()
    {
        $assess_id = $this->task_instance->getTask()->getQuiz()->getId();
        if ($assess_id) {

            $mapper   = QuizMapper::getInstance();
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

            try {
                /** @var Quiz $oldAssess */
                $oldAssess = $mapper->findById($assess_id);
                // And update the mapper version
                /** @var Quiz $newAssess */
                $newAssess = $mapper->findById($newAssessId);
            }
            catch (\Exception $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
            }

            // Check if we need to override the dates

            $quizDates = $this->task_instance->getUnitInstance()->getQuizDates();
            $datesOverridden = false;

            if (is_array($quizDates)) {
                $assessment = QuizMapper::getInstance()->findById($assess_id);
                $task_id = $assessment->getTask()->getId();

                if (isset($quizDates[$task_id])) {
                    $datesOverridden = true;
                    $newAssess->setDatesFromForm($quizDates[$task_id]);
                }
            }

            $newAssess
                ->setTask(null)
                ->setTaskInstance($this->task_instance)
                ->setUser($this->task_instance->getOwner() ?: $_SESSION['user'])
                ->setCurriculumNodes($oldAssess->getCurriculumNodes())
            ;
            if (!$datesOverridden) {
                $newAssess
                    ->setStart($this->getOpenTimeFromTask())
                    ->setFinish($this->getAssessmentCloseTimeFromTask());
            }
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

            // Check if we need to override the dates

            $workDates = $this->task_instance->getUnitInstance()->getWorkDates();
            $datesOverridden = false;

            if (is_array($workDates)) {
                $box = SubmissionBoxMapper::getInstance()->findById($box_id);
                $task_id = $box->getTask()->getId();

                if (isset($workDates[$task_id])) {
                    $datesOverridden = true;
                    $box->setDatesFromForm($workDates[$task_id]);
                }
            }

            $box
                ->setTask(null)
                ->setTaskInstance($this->task_instance)
                ->setOwner($this->task_instance->getOwner() ?: $_SESSION['user'])
                ->setCategory($this->category)
            ;
            if (!$datesOverridden) {
                $box
                    ->setPublishDate($this->getOpenTimeFromTask())
                    ->setOpenDate($this->getOpenTimeFromTask())
                    ->setDueDate($this->getSubmissionBoxCloseTimeFromTask())
                    ->setLateDate($this->getSubmissionBoxCloseTimeFromTask());
            }
            SubmissionBoxMapper::getInstance()->create($box);

            // Clone rubrix too
            SubmissionBox\Rubric\UsageMapper::getInstance()
                ->copyFromCopiedSubmissionBox($box_id, $box->getId());
            SubmissionBox\Rubric\DescriptorMapper::getInstance()
                ->copyFromCopiedSubmissionBox($box_id, $box->getId());
            SubmissionBox\Rubric\LevelMapper::getInstance()
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
                /** @var Quiz $assess_object */
                $assess_object = QuizMapper::getInstance()->findById($assess_id);
                $assess_object->setStart($this->getOpenTimeFromTask());
                QuizMapper::getInstance()->save($assess_object);
            }
        } catch (MapperException\Deleted $e) {
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
        } catch (MapperException\Deleted $e) {
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
                /** @var Quiz $assess_object */
                $closeTime = $this->getAssessmentCloseTimeFromTask();
                $assess_object = QuizMapper::getInstance()->findById($assess_id);
                // If this item's close time is the same as the task's close time,
                // update it
                $assess_object->setFinish($closeTime);
                QuizMapper::getInstance()->save($assess_object);
            }
        } catch (MapperException\Deleted $e) {
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
        } catch (MapperException\Deleted $e) {
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
