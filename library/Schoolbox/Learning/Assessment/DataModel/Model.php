<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as Project;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Model as Reporting;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\LTI\Assessment\Model as LtiAssessment;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;
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
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $publishDate = null;

    /**
     * The date/time at which participants able to create responses to this assessment.
     *
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $openDate = null;

    /**
     * The date/time after which a participant's response will be marked as late.
     *
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $dueDate = null;

    /**
     * The date/time at which participants are no longer able to create responses to this assessment.
     *
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $closeDate = null;

    /**
     * The comparative amount that this assessment affects a student's grade by.
     *
     * @var float
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
     * A flag denoting whether this assessment is course managed (1) to prevent teachers
     * from getting too creative with the assessment's details; or not course managed (0)
     * which will let teachers customise the assessment's fields in the modify screen
     * after it's been imported
     *
     * @var bool|null
     */
    private ?bool $courseManaged = null;


    /**
     * A flag denoting whether this assessment requires the SEB Safe Exam Browser
     *
     * @var bool|null
     */
    private ?bool $sebEnabled = null;

    /**
     * @var int|null
     */
    private ?int $originalAssessmentId = null;

    private ?Model $originalAssessment = null;

    /**
     * The task which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     *
     * @var null|Task
     */
    protected $task;

    /**
     * The due work which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     *
     * @var null|DueWork
     */
    protected $dueWork;

    /**
     * The quiz which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     *
     * @var null|Quiz
     */
    protected $quiz;

    /**
     * The LTI assessment which may or may not be associated with this
     * assessment.
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     */
    private ?LtiAssessment $ltiAssessment = null;


    /**
     * The Lesson Plan which might exist for this assessment
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     *
     * @var LessonPlan|null
     */
    private ?LessonPlan $lessonPlan = null;


    /**
     * The Dialog Agent which might exist for this assessment
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     *
     * @var DialogAgent|null
     */
    private ?DialogAgent $dialogAgent = null;


    /**
     * The Reporting which might exist for this assessment
     *
     * Only one of task, due work, quiz, LTI assessment, Lesson Plan, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
     *
     * @var Reporting|null
     */
    private ?Reporting $reporting = null;


    /**
     * The project which may or may not be associated with this assessment.
     *
     * Only one of task, due work, quiz, LTI assessment, Dialog Agent, Reporting or project may be
     * associated with a single assessment.
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


    /**
     * @var null|GenericCollection<\Schoolbox\Assessment\Rubric\Instance\Model> $rubricInstances
     */
    private ?GenericCollection $rubricInstances = null;


    /**
     * Constructor
     */
    public function __construct()
    {
    }


    /**
     * Runs when you do $x = clone $y
     *
     * @return void
     */
    public function __clone()
    {
        $this->id = null;

        if ($this->hasQuiz()) {
            $this->quiz = clone $this->quiz;
        }

        if ($this->hasDueWork()) {
            $this->dueWork = clone $this->dueWork;
        }

        if ($this->hasTask()) {
            $this->task = clone $this->task;
        }

        if ($this->hasLtiAssessment()) {
            $this->ltiAssessment = clone $this->ltiAssessment;
        }

        if ($this->hasProject()) {
            $this->project = clone $this->project;
        }

        if ($this->hasLessonPlan()) {
            $this->lessonPlan = clone $this->lessonPlan;
        }

        if ($this->hasDialogAgent()) {
            $this->dialogAgent = clone $this->dialogAgent;
        }

        if ($this->hasReporting()) {
            $this->reporting = clone $this->reporting;
        }
    }


    /**
     * Helper method for getting this assessment data model's context.
     *
     * @return null|IAssessmentContextDataModel
     */
    public function getAssessmentContext(): ?IAssessmentContextDataModel
    {
        $folder   = $this->getContextFolder();
        $unitSpec = $this->getContextUnitSpecification();
        $unitImpl = $this->getContextUnitImplementation();
        $project  = $this->getContextProject();

        if (isset($folder)) {
            return $folder;
        }

        if (isset($unitSpec)) {
            return $unitSpec;
        }

        if (isset($unitImpl)) {
            return $unitImpl;
        }

        if (isset($project)) {
            return $project;
        }

        return null;
    }


    /**
     * Helper method for getting this assessment data model's context.
     *
     * @param IAssessmentContextDataModel|null $context
     * @return static
     */
    public function setAssessmentContext(?IAssessmentContextDataModel $context = null)
    {
        if ($context instanceof Folder) {
            return $this->setContextFolder($context)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification(null)
                        ->setContextProject(null);
        }

        if ($context instanceof UnitSpecification) {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification($context)
                        ->setContextProject(null);
        }

        if ($context instanceof UnitImplementation) {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation($context)
                        ->setContextUnitSpecification(null)
                        ->setContextProject(null);
        }

        if ($context instanceof Project) {
            return $this->setContextFolder(null)
                        ->setContextUnitImplementation(null)
                        ->setContextUnitSpecification(null)
                        ->setContextProject($context);
        }

        return $this->setContextFolder(null)
                    ->setContextUnitImplementation(null)
                    ->setContextUnitSpecification(null)
                    ->setContextProject(null);
    }

    /**
     * Does this have a task?
     *
     * @return bool
     */
    public function hasTask(): bool
    {
        return ($this->getTask() instanceof Task);
    }

    /**
     * Does this have a quiz?
     *
     * @return bool
     */
    public function hasQuiz(): bool
    {
        return ($this->getQuiz() instanceof Quiz);
    }

    /**
     * Does this have a due work?
     *
     * @return bool
     */
    public function hasDueWork(): bool
    {
        return ($this->getDueWork() instanceof DueWork);
    }

    /**
     * Does this have an LTI assessment?
     *
     * @return bool
     */
    public function hasLtiAssessment(): bool
    {
        return ($this->getLtiAssessment() instanceof LtiAssessment);
    }

    /**
     * Does this have a project?
     *
     * This is whether this is a project, not whether this is within the context of one.
     *
     * @return bool
     */
    public function hasProject(): bool
    {
        return ($this->getProject() instanceof Project);
    }


    /**
     * Does this have a Lesson Plan
     *
     * @return bool
     */
    public function hasLessonPlan(): bool
    {
        return ($this->getLessonPlan() instanceof LessonPlan);
    }


    /**
     * Does this have a Dialog Agent
     *
     * @return bool
     */
    public function hasDialogAgent(): bool
    {
        return ($this->getDialogAgent() instanceof DialogAgent);
    }


    /**
     * Does this have a Reporting
     *
     * @return bool
     */
    public function hasReporting(): bool
    {
        return ($this->getReporting() instanceof Reporting);
    }


    /**
     * Does this have any sub-objects?
     *
     * @return bool
     */
    public function hasSubObject(): bool
    {
        /* Okay this is a bit ridiculous
         */
        return $this->hasTask() ||
            $this->hasDueWork() ||
            $this->hasQuiz() ||
            $this->hasProject() ||
            $this->hasLessonPlan() ||
            $this->hasDialogAgent() ||
            $this->hasReporting();
    }

    /**
     * Gets the sub-object for this model (task, quiz, duework, project), or
     * null if somehow no model is associated.
     *
     * TODO figure out a better name than subobject
     *
     * @return IAssessableDataModel|null
     */
    public function getSubObject(): ?IAssessableDataModel
    {
        if ($this->hasDueWork()) {
            return $this->getDueWork();
        }

        if ($this->hasQuiz()) {
            return $this->getQuiz();
        }

        if ($this->hasLtiAssessment()) {
            return $this->getLtiAssessment();
        }

        if ($this->hasProject()) {
            return $this->getProject();
        }

        if ($this->hasTask()) {
            // this test must go last because old course-based quizzes and works register as "task == true" :(
            return $this->getTask();
        }

        if ($this->hasLessonPlan()) {
            return $this->getLessonPlan();
        }

        if ($this->hasDialogAgent()) {
            return $this->getDialogAgent();
        }

        if ($this->hasReporting()) {
            return $this->getReporting();
        }

        return null;
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
        }

        if ($subObject instanceof DueWork) {
            return $this->setDueWork($subObject);
        }

        if ($subObject instanceof Quiz) {
            return $this->setQuiz($subObject);
        }

        if ($subObject instanceof LtiAssessment) {
            return $this->setLtiAssessment($subObject);
        }

        if ($subObject instanceof Project) {
            return $this->setProject($subObject);
        }

        if ($subObject instanceof LessonPlan) {
            return $this->setLessonPlan($subObject);
        }

        if ($subObject instanceof DialogAgent) {
            return $this->setDialogAgent($subObject);
        }

        if ($subObject instanceof Reporting) {
            return $this->setReporting($subObject);
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
     * @return Alaress_Date_Format|null
     */
    public function getPublishDate(): ?Alaress_Date_Format
    {
        return $this->publishDate;
    }

    /**
     * @param Alaress_Date_Format|null $publishDate
     *
     * @return static
     */
    public function setPublishDate(?Alaress_Date_Format $publishDate): self
    {
        $this->publishDate = $publishDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getOpenDate(): ?Alaress_Date_Format
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format|null $openDate
     *
     * @return static
     */
    public function setOpenDate(?Alaress_Date_Format $openDate): self
    {
        $this->openDate = $openDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getDueDate(): ?Alaress_Date_Format
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format|null $dueDate
     *
     * @return static
     */
    public function setDueDate(?Alaress_Date_Format $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getCloseDate(): ?Alaress_Date_Format
    {
        return $this->closeDate;
    }

    /**
     * @param Alaress_Date_Format|null $closeDate
     *
     * @return static
     */
    public function setCloseDate(?Alaress_Date_Format $closeDate): self
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
     * @param float $weight
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
     * @param WorkType|null $workType
     * @return static
     */
    public function setWorkType(?WorkType $workType = null)
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
     * @param Task|null $task
     * @return static
     */
    public function setTask(?Task $task = null)
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
     * @param DueWork|null $dueWork
     * @return static
     */
    public function setDueWork(?DueWork $dueWork = null)
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
     * @param Quiz|null $quiz
     * @return static
     */
    public function setQuiz(?Quiz $quiz = null)
    {
        $this->quiz = $quiz;
        return $this;
    }

    /**
     * @return LtiAssessment|null
     */
    public function getLtiAssessment(): ?LtiAssessment
    {
        return $this->ltiAssessment;
    }

    /**
     * @param LtiAssessment|null $ltiAssessment
     * @return static
     */
    public function setLtiAssessment(?LtiAssessment $ltiAssessment = null): self
    {
        $this->ltiAssessment = $ltiAssessment;
        return $this;
    }


    /**
     * @return LessonPlan|null
     */
    public function getLessonPlan(): ?LessonPlan
    {
        return $this->lessonPlan;
    }


    /**
     * @param LessonPlan|null $lessonPlan
     * @return $this
     */
    public function setLessonPlan(?LessonPlan $lessonPlan = null): self
    {
        $this->lessonPlan = $lessonPlan;
        return $this;
    }


    /**
     * @return DialogAgent|null
     */
    public function getDialogAgent(): ?DialogAgent
    {
        return $this->dialogAgent;
    }


    /**
     * @param DialogAgent|null $dialogAgent
     * @return $this
     */
    public function setDialogAgent(?DialogAgent $dialogAgent): self
    {
        $this->dialogAgent = $dialogAgent;
        return $this;
    }


    /**
     * @return Reporting|null
     */
    public function getReporting(): ?Reporting
    {
        return $this->reporting;
    }


    /**
     * @param Reporting|null $reporting
     * @return $this
     */
    public function setReporting(?Reporting $reporting): self
    {
        $this->reporting = $reporting;
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
     * @param Project|null $project
     * @return static
     */
    public function setProject(?Project $project = null)
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
     * @param Folder|null $contextFolder
     * @return static
     */
    public function setContextFolder(?Folder $contextFolder = null)
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
     * @param UnitSpecification|null $contextUnitSpecification
     * @return static
     */
    public function setContextUnitSpecification(?UnitSpecification $contextUnitSpecification = null)
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
     * @param UnitImplementation|null $contextUnitImplementation
     * @return static
     */
    public function setContextUnitImplementation(?UnitImplementation $contextUnitImplementation = null)
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
     * @param Project|null $contextProject
     * @return static
     */
    public function setContextProject(?Project $contextProject = null)
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
     * @param Model|null $specificationAssessment
     * @return static
     */
    public function setSpecificationAssessment(?Model $specificationAssessment = null)
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
     * @return GenericCollection<\Schoolbox\Assessment\Rubric\Instance\Model>|null
     */
    public function getRubricInstances(): ?GenericCollection
    {
        return $this->rubricInstances;
    }


    /**
     * @param GenericCollection<\Schoolbox\Assessment\Rubric\Instance\Model>|null $rubricInstances
     * @return $this
     */
    public function setRubricInstances(?GenericCollection $rubricInstances): self
    {
        $rubricInstances->assertCollectionOf(\Schoolbox\Assessment\Rubric\Instance\Model::class);
        $this->rubricInstances = $rubricInstances;
        return $this;
    }


    /**
     * Returns just the Curriculum nodes that do not have an empty code.
     * usually these are parent objects, or titles
     *
     * @return Node\Model[]|GenericCollection
     */
    public function getViewableCurriculumNodes(): GenericCollection
    {
        return $this->curriculumNodes->filter(
            function ($node) {
                if ($node->getCode() !== '') {
                    return true;
                } else {
                    return false;
                }
            }
        );
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
     * @return bool|null
     */
    public function getCourseManaged(): ?bool
    {
        return $this->courseManaged;
    }


    /**
     * @param bool|null $courseManaged
     * @return $this
     */
    public function setCourseManaged(?bool $courseManaged): self
    {
        $this->courseManaged = $courseManaged;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getSebEnabled(): ?bool
    {
        return $this->sebEnabled;
    }


    /**
     * @param bool|null $sebEnabled
     * @return Model
     */
    public function setSebEnabled(?bool $sebEnabled): self
    {
        $this->sebEnabled = $sebEnabled;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOriginalAssessmentId(): ?int
    {
        return $this->originalAssessmentId;
    }

    public function getOriginalAssessment(): ?Model
    {
        return $this->originalAssessment;
    }

    public function setOriginalAssessmentId(?int $originalAssessmentId): self
    {
        $this->originalAssessmentId = $originalAssessmentId;
        return $this;
    }

    public function setOriginalAssessment(?Model $originalAssessment): self
    {
        $this->originalAssessment = $originalAssessment;
        return $this;
    }
}
