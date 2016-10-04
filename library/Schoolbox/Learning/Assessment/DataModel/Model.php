<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Assessment\Component\OnlineAssessment as Quiz;
use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Class Model
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * A descriptive title of the assessment.
     *
     * @var string
     */
    protected $title;

    /**
     * A longer description of the assessment.
     *
     * @var string
     */
    protected $description;

    /**
     * The date/time at which this assessment become visible to its participants.
     *
     * @var Alaress_Date_Format
     */
    protected $publishDate;

    /**
     * The date/time at which participants able to create responses to this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * The date/time after which a participant's response will be marked as late.
     *
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * The date/time at which participants are no longer able to create responses to this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $closeDate;

    /**
     * The comparative amount that this assessment affects a student's grade by.
     *
     * @var int
     */
    protected $weight = 0;

    /**
     * The sort order of this assessment within its container
     * (a class page, course specification or implementation or project).
     *
     * Assessments are sorted by sequence in ascending order.
     *
     * @var int
     */
    protected $sequence = 0;

    /**
     * The task which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var Task
     */
    protected $task;

    /**
     * The due work which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var DueWork
     */
    protected $dueWork;

    /**
     * The quiz which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var Quiz
     */
    protected $quiz;

    /**
     * The project which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var Project
     */
    protected $project;

    /**
     * This assessment may be attached to a folder.
     *
     * @var Folder
     */
    protected $folder;

    /**
     * This assessment may be attached to a unit specification.
     *
     * @var UnitSpecification
     */
    protected $unitSpecification;

    /**
     * This assessment may be attached to a unit implementation.
     *
     * @var UnitImplementation
     */
    protected $unitImplementation;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->publishDate = new Alaress_Date_NullDate();
        $this->openDate    = new Alaress_Date_NullDate();
        $this->dueDate     = new Alaress_Date_NullDate();
        $this->closeDate   = new Alaress_Date_NullDate();
        $this->createdAt   = new Alaress_Date_NullDate();
        $this->updatedAt   = new Alaress_Date_NullDate();
        $this->deletedAt   = new Alaress_Date_NullDate();
    }

    /**
     * Helper method for getting this assessment data model's context.
     *
     * @return IAssessmentContextDataModel
     */
    public function getAssessmentContext()
    {
        $folder   = $this->getFolder();
        $unitSpec = $this->getUnitSpecification();
        $unitImpl = $this->getUnitImplementation();
        if (isset($folder)) {
            return $folder;
        } elseif (isset($unitSpec)) {
            return $unitSpec;
        } elseif (isset($unitImpl)) {
            return $unitImpl;
        }
        return null;
    }

    /**
     * Helper method for getting this assessment data model's context.
     *
     * @param IAssessmentContextDataModel $context
     * @return static
     */
    public function setAssessmentContext(IAssessmentContextDataModel $context = null)
    {
        if ($context instanceof Folder) {
            return $this->setFolder($context)
                        ->setUnitImplementation(null)
                        ->setUnitSpecification(null);
        } elseif ($context instanceof UnitSpecification) {
            return $this->setFolder(null)
                        ->setUnitImplementation(null)
                        ->setUnitSpecification($context);
        } elseif ($context instanceof UnitImplementation) {
            return $this->setFolder(null)
                        ->setUnitImplementation($context)
                        ->setUnitSpecification(null);
        } else {
            return $this->setFolder(null)
                        ->setUnitImplementation(null)
                        ->setUnitSpecification(null);
        }
    }

    /**
     * Does this have a task?
     *
     * @return bool
     */
    public function hasTask()
    {
        return ($this->getTask() instanceof Task);
    }

    /**
     * Does this have a quiz?
     *
     * @return bool
     */
    public function hasQuiz()
    {
        return ($this->getQuiz() instanceof Quiz);
    }

    /**
     * Does this have a due work?
     *
     * @return bool
     */
    public function hasDueWork()
    {
        return ($this->getDueWork() instanceof DueWork);
    }

    /**
     * Does this have a project?
     *
     * @return bool
     */
    public function hasProject()
    {
        return ($this->getProject() instanceof Project);
    }

    /**
     * Does this have any sub-objects?
     *
     * @return bool
     */
    public function hasSubObject()
    {
        return $this->hasTask() || $this->hasDueWork() || $this->hasQuiz() || $this->hasProject();
    }

    /**
     * Gets the sub-object for this model (task, quiz, duework, project)
     *
     * TODO figure out a better name than subobject
     *
     * @return IAssessableDataModel
     * @throws \UnexpectedValueException if there's no sub-object
     */
    public function getSubObject()
    {
        if ($this->hasTask()) {
            return $this->getTask();
        }
        if ($this->hasDueWork()) {
            return $this->getDueWork();
        }
        if ($this->hasQuiz()) {
            return $this->getQuiz();
        }
        if ($this->hasProject()) {
            return $this->getProject();
        }
        throw new \UnexpectedValueException('This object has no sub-object');
    }

    /**
     * Sets the sub-object for this model (task, quiz, duework, project)
     *
     * @param IAssessableDataModel $subObject
     * @return static
     */
    public function setSubObject(IAssessableDataModel $subObject)
    {
        if ($subObject instanceof Task) {
            return $this->setTask($subObject);
        } elseif ($subObject instanceof DueWork) {
            return $this->setDueWork($subObject);
        } elseif ($subObject instanceof Quiz) {
            return $this->setQuiz($subObject);
        } elseif ($subObject instanceof Project) {
            return $this->setProject($subObject);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return static
     */
    public function setPublishDate(Alaress_Date_Format $publishDate = null)
    {
        $this->publishDate = $publishDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format $openDate
     * @return static
     */
    public function setOpenDate(Alaress_Date_Format $openDate = null)
    {
        $this->openDate = $openDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format $dueDate
     * @return static
     */
    public function setDueDate(Alaress_Date_Format $dueDate = null)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCloseDate()
    {
        return $this->closeDate;
    }

    /**
     * @param Alaress_Date_Format $closeDate
     * @return static
     */
    public function setCloseDate($closeDate)
    {
        $this->closeDate = $closeDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     * @return static
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param Task $task
     * @return static
     */
    public function setTask(Task $task = null)
    {
        $this->task = $task;
        return $this;
    }

    /**
     * @return DueWork
     */
    public function getDueWork()
    {
        return $this->dueWork;
    }

    /**
     * @param DueWork $dueWork
     * @return static
     */
    public function setDueWork(DueWork $dueWork = null)
    {
        $this->dueWork = $dueWork;
        return $this;
    }

    /**
     * @return Quiz
     */
    public function getQuiz()
    {
        return $this->quiz;
    }

    /**
     * @param Quiz $quiz
     * @return static
     */
    public function setQuiz(Quiz $quiz = null)
    {
        $this->quiz = $quiz;
        return $this;
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param Project $project
     * @return static
     */
    public function setProject(Project $project = null)
    {
        $this->project = $project;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param Folder $folder
     * @return static
     */
    public function setFolder(Folder $folder = null)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return UnitSpecification
     */
    public function getUnitSpecification()
    {
        return $this->unitSpecification;
    }

    /**
     * @param UnitSpecification $unitSpecification
     * @return static
     */
    public function setUnitSpecification(UnitSpecification $unitSpecification = null)
    {
        $this->unitSpecification = $unitSpecification;
        return $this;
    }

    /**
     * @return UnitImplementation
     */
    public function getUnitImplementation()
    {
        return $this->unitImplementation;
    }

    /**
     * @param UnitImplementation $unitImplementation
     * @return static
     */
    public function setUnitImplementation(UnitImplementation $unitImplementation = null)
    {
        $this->unitImplementation = $unitImplementation;
        return $this;
    }
}
