<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Quiz\Model as Quiz;
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
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Curriculum\Node;
use TypeError;

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
     * Assessments are sorted by sequence in ascending order, starting from 1.
     * A value of 0 will trigger the mapper to auto-assign the next available integer.
     *
     * @var int
     */
    protected $sequence = 0;

    /**
     * It is valid to be null. This means 'None' - and would be the only possible value if
     * the WorkType list has not been populated in the Admin
     *
     * @var null|WorkType
     */
    private $workType;

    /**
     * @var int
     */
    private $defaultOpenSpan;

    /**
     * The task which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var null|Task
     */
    protected $task;

    /**
     * The due work which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var null|DueWork
     */
    protected $dueWork;

    /**
     * The quiz which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var null|Quiz
     */
    protected $quiz;

    /**
     * The project which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz or project may be associated with a
     * single assessment.
     *
     * @var null|Project
     */
    protected $project;

    /**
     * This assessment may live within the context of a folder.
     *
     * An assessment may only be within a single context.
     *
     * @var null|Folder
     */
    protected $contextFolder;

    /**
     * This assessment may live within the context of a unit specification.
     *
     * An assessment may only be within a single context.
     *
     * @var null|UnitSpecification
     */
    protected $contextUnitSpecification;

    /**
     * This assessment may live within the context of a unit implementation.
     *
     * An assessment may only be within a single context.
     *
     * @var null|UnitImplementation
     */
    protected $contextUnitImplementation;

    /**
     * This assessment may live within the context of a project.
     *
     * An assessment may only be within a single context.
     *
     * @var null|Project
     */
    protected $contextProject;

    /**
     * If this assessment is attached to a unit implementation, it may have
     * been based off of a specification assessment.
     *
     * It will not have a Spec if it was created directly in the unit implementation.
     *
     * @var null|Model
     */
    protected $specificationAssessment;

    /**
     * A collection of Curriculum Nodes
     *
     * @var Node\Model[]|GenericCollection
     */
    private $curriculumNodes;

    /** @var Plan\Model[]|GenericCollection */
    private $plans;

    /** @var null|string */
    private $externalId;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->publishDate     = new Alaress_Date_NullDate();
        $this->openDate        = new Alaress_Date_NullDate();
        $this->dueDate         = new Alaress_Date_NullDate();
        $this->closeDate       = new Alaress_Date_NullDate();
        $this->createdAt       = new Alaress_Date_NullDate();
        $this->updatedAt       = new Alaress_Date_NullDate();
        $this->deletedAt       = new Alaress_Date_NullDate();
    }

    function __clone()
    {
        $this->id = null;
        if($this->hasQuiz()){
            $this->quiz = clone $this->quiz;
        }
        if($this->hasDueWork()){
            $this->dueWork = clone $this->dueWork;
        }
        if($this->hasTask()){
            $this->task = clone $this->task;
        }
        if($this->hasProject()){
            $this->project = clone $this->project;
        }
    }

    /**
     * Helper method for getting this assessment data model's context.
     *
     * @return IAssessmentContextDataModel
     */
    public function getAssessmentContext()
    {
        $folder   = $this->getContextFolder();
        $unitSpec = $this->getContextUnitSpecification();
        $unitImpl = $this->getContextUnitImplementation();
        $project  = $this->getContextProject();
        if (isset($folder)) {
            return $folder;
        } elseif (isset($unitSpec)) {
            return $unitSpec;
        } elseif (isset($unitImpl)) {
            return $unitImpl;
        } elseif (isset($project)) {
            return $project;
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
            return $this->setContextFolder($context)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification(null)
                        ->setContextProject(null);
        } elseif ($context instanceof UnitSpecification) {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification($context)
                        ->setContextProject(null);
        } elseif ($context instanceof UnitImplementation) {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation($context)
                        ->setContextUnitSpecification(null)
                        ->setContextProject(null);
        } elseif ($context instanceof Project) {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification(null)
                        ->setContextProject($context);
        } else {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification(null)
                        ->setContextProject(null);
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
     * This is whether this is a project, not whether this is within the context of one.
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
        if ($this->hasDueWork()) {
            return $this->getDueWork();
        }
        if ($this->hasQuiz()) {
            return $this->getQuiz();
        }
        if ($this->hasProject()) {
            return $this->getProject();
        }
        if ($this->hasTask()) {
            // this test must go last because old course-based quizzes and works register as "task == true" :(
            return $this->getTask();
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
     * @return null|WorkType
     */
    public function getWorkType()
    {
        return $this->workType;
    }


    /**
     * @param null|WorkType $workType
     * @return static
     */
    public function setWorkType(WorkType $workType = null)
    {
        $this->workType = $workType;
        return $this;
    }

    /**
     * @return int
     */
    public function getDefaultOpenSpan()
    {
        return $this->defaultOpenSpan;
    }

    /**
     * @param int $defaultOpenSpan
     * @return static
     */
    public function setDefaultOpenSpan($defaultOpenSpan)
    {
        $this->defaultOpenSpan = $defaultOpenSpan;
        return $this;
    }

    /**
     * @return null|Task
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
     * @return null|DueWork
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
     * @return null|Quiz
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
     * @return null|Project
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
     * @return null|Folder
     */
    public function getContextFolder()
    {
        return $this->contextFolder;
    }

    /**
     * @param Folder $contextFolder
     * @return static
     */
    public function setContextFolder(Folder $contextFolder = null)
    {
        $this->contextFolder = $contextFolder;
        return $this;
    }

    /**
     * @return null|UnitSpecification
     */
    public function getContextUnitSpecification()
    {
        return $this->contextUnitSpecification;
    }

    /**
     * @param UnitSpecification $contextUnitSpecification
     * @return static
     */
    public function setContextUnitSpecification(UnitSpecification $contextUnitSpecification = null)
    {
        $this->contextUnitSpecification = $contextUnitSpecification;
        return $this;
    }

    /**
     * @return null|UnitImplementation
     */
    public function getContextUnitImplementation()
    {
        return $this->contextUnitImplementation;
    }

    /**
     * @param UnitImplementation $contextUnitImplementation
     * @return static
     */
    public function setContextUnitImplementation(UnitImplementation $contextUnitImplementation = null)
    {
        $this->contextUnitImplementation = $contextUnitImplementation;
        return $this;
    }

    /**
     * @return null|Project
     */
    public function getContextProject()
    {
        return $this->contextProject;
    }

    /**
     * @param Project $contextProject
     * @return static
     */
    public function setContextProject(Project $contextProject = null)
    {
        $this->contextProject = $contextProject;
        return $this;
    }

    /**
     * @return null|Model
     */
    public function getSpecificationAssessment()
    {
        return $this->specificationAssessment;
    }

    /**
     * @param Model $specificationAssessment
     * @return static
     */
    public function setSpecificationAssessment(Model $specificationAssessment = null)
    {
        $this->specificationAssessment = $specificationAssessment;
        return $this;
    }


    /**
     * @return Node\Model[]|GenericCollection
     */
    public function getCurriculumNodes()
    {
        return $this->curriculumNodes;
    }

    /**
     * @param Node\Model[]|GenericCollection $curriculumNodes
     * @return $this
     * @throws TypeError
     */
    public function setCurriculumNodes(GenericCollection $curriculumNodes)
    {
        $curriculumNodes->assertCollectionOf(Node\Model::class);
        $this->curriculumNodes = $curriculumNodes;
        return $this;
    }

    /**
     * @return Plan\Model[]|GenericCollection
     */
    public function getPlans()
    {
        return $this->plans;
    }

    /**
     * @param Plan\Model[]|GenericCollection $plans
     * @return $this
     * @throws TypeError
     */
    public function setPlans(GenericCollection $plans)
    {
        $plans->assertCollectionOf(Plan\Model::class);
        $this->plans = $plans;
        return $this;
    }


    /**
     * Returns whether this assessment's dynamic plan - if it has one
     *
     * @return null|Plan\Model
     */
    public function getDynamicPlan(): ?Plan\Model
    {
        foreach ($this->plans as $plan) {
            if ($plan->isDynamic()) {
                return $plan;
            }
        }
        return null;
    }


    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }
}
