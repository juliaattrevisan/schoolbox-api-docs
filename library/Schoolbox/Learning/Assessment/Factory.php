<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use Alaress_Date_Format;
use DomainException;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Error;
use Schoolbox\Error\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Decorator\CourseManagedDecorator as DueWorkCourseManagedDecorator;
use Schoolbox\Learning\Assessment\DueWork\Decorator\Decorator as DueWorkDecorator;
use Schoolbox\Learning\Assessment\DueWork\Decorator\DueWorkTrait;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Lti\Decorator\Decorator as LtiDecorator;
use Schoolbox\Learning\Assessment\Lti\Decorator\LtiTrait;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\LessonPlan\Decorator\LessonPlanTrait;
use Schoolbox\Learning\Assessment\Project\Decorator\Decorator as ProjectDecorator;
use Schoolbox\Learning\Assessment\Project\Decorator\ProjectTrait;
use Schoolbox\Learning\Assessment\Quiz\Decorator\Decorator as QuizDecorator;
use Schoolbox\Learning\Assessment\Quiz\Decorator\QuizTrait;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Summator\Factory as SummatorFactory;
use Schoolbox\Learning\Assessment\Task\Decorator\Decorator as TaskDecorator;
use Schoolbox\Learning\Assessment\Task\Decorator\TaskTrait;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * Build an IAssessable instance based on the common params
 *
 * The IAssessable instance can later be decorated with their
 * additional specific details
 *
 * @package Schoolbox\Learning\Assessment
 */
class Factory
{
    use DueWorkTrait;
    use QuizTrait;
    use TaskTrait;
    use ProjectTrait;
    use AttachedFilesTrait;
    use LtiTrait;
    use LessonPlanTrait;

    /**
     * Unique identifier
     *
     * @var string
     */
    protected $assessmentType;

    /**
     * What context is this within?
     *
     *
     * @var AssessmentContext
     */
    protected $context;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var null|int
     */
    private $workType;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var User
     */
    protected $creator;

    /**
     * @var float|int
     */
    protected $weight;

    /**
     * @var int
     */
    protected $defaultOpenSpan;

    /**
     * @var bool
     */
    protected $courseManaged;


    /**
     * @var bool
     */
    private bool $sebEnabled;

    /**
     * @var int|null
     */
    private ?int $originalAssessmentId;

    /**
     * @var IAssessable|null
     */
    private ?IAssessable $originalAssessment = null;

    /**
     * @var string
     */
    protected $publishAt;

    /**
     * @var string
     */
    protected $openAt;

    /**
     * @var string
     */
    protected $dueAt;

    /**
     * @var string
     */
    protected $closeAt;

    /**
     * @var int
     */
    private $markVisibility;

    /**
     * @var array
     */
    protected $curriculumNodes;

    /** @var IAssessable */
    private $original;


    /**
     * Idiosyncratic usage in Lesson Plans
     * Used in Lesson Plans' AI generation to decide how much data the LLM should generate. Unfortunately shares a
     * similar name, but not concept, with other duration fields here
     *
     * (Used by Lesson Plans, Quiz and Dialog Agents)
     *
     * @var string|null
     */
    private ?string $duration = null;


    /**
     * Dialog Agents have a scenario
     *
     * (Dialog Agent specific)
     *
     * @var int|null
     */
    private ?int $scenarioId = null;


    /**
     * Supporting Data Enable
     * A bool that basically represents the mega markbook and other academic details. This is optional; some reporting
     * activities will not require it (such as a reporting activity that records someone attendances)
     *
     * (Reporting activity specific)
     */
    private ?bool $supportingDataEnable = null;


    /**
     * Supporting Data Date Range
     * The multiple options here help inform how we derive From and To dates for the swamp to retrieve data.
     * This date range helps constrain how much data is fetched, hydrated and collated in the mega markbook for
     * performance reasons...since we're looking at a LOT of objects here, anything we do to minimise waste is nice
     *
     * (Reporting activity specific)
     */
    private ?array $supportingDataRange = null;


    /**
     * Supporting Data Curriculum Nodes
     * The work types option here helps constrain the mega markbook to look in particular folders for assessments that
     * were associated with any of the selected curriculum to minimise wasted hydration
     *
     * Note that this might be a parent node containing multiple descendents and not have its own curriculum code: such
     * a node would be a subject area or subtree of a curriculum hierarchy.. If chosen, we'll look for assessments that
     * were associated with *any* of this node's descendents.
     *
     * (Reporting activity specific)
     *
     * @var array|null
     */
    private ?array $supportingDataCurriculumNodes = null;


    /**
     * Supporting Data Folders
     * The folder option here helps constrain the mega markbook to look in particular folders for assessments that were
     * due within the Supporting Data Date Range and for feedbacks that were published within the Supporting Data Date
     * Range, to minimise wasted hydration
     *
     * (Reporting activity specific)
     */
    private ?string $supportingDataFolders = null;


    /**
     * Supporting Data Work Types
     * The work types option here helps constrain the mega markbook to look in particular folders for assessments that
     * were associated with any of the selected worktypes to minimise wasted hydration
     *
     * (Reporting activity specific)
     */
    private ?array $supportingDataWorkTypes = null;


    /**
     * Supporting Data Activity Grades
     * This decides whether to render the mega aggregation of students' grades across all their markbooks as derived by
     * the folder/date/worktype selected
     *
     * This is optional for Reporting activities that aren't specifically academically oriented, such as attendances
     *
     * (Reporting activity specific)
     * @var bool|null
     */
    private ?bool $supportingDataMegamarkboook = null;


    /**
     * Feedback Comments Enable
     * This decides whether to provide a ckeditor to staff for writing arbitrary Comments in the feedback area. This is
     * optional for all those reporting activities that just tell you a value, rather than provide any meaningful
     * feedback, such as attendance data.
     *
     * @var bool|null
     */
    private ?bool $feedbackCommentsEnable = null;


    /**
     * Factory constructor.
     * @param User $creator
     * @param array $post (an array of post vars from the create/update forms)
     * @param string|null $assessmentType
     * @param IAssessable|null $original
     * @param Mapper $errorMapper
     * @throws \Exception
     */
    public function __construct(
        User $creator,
        array $post,
        ?string $assessmentType,
        ?IAssessable $original,
        private \Schoolbox\Error\Mapper $errorMapper
    ) {
        $this->setAssessmentType(trim($post['assessmentType'] ?? ''));
        $this->setContext($post['context'] ?? '');
        $this->setTitle(trim($post['title'] ?? ''));
        $this->setDescription(trim($post['description'] ?? ''));
        $this->setCreator($creator);
        $this->setWeight((float)(trim($post['weight'] ?? '')));
        $this->setCurriculumNodes($post['curriculumCodes'] ?? []);
        $this->setDefaultOpenSpan(empty($post['default_open_span']) ? null : intval($post['default_open_span']));
        $this->setCourseManaged((bool)($post['courseManaged'] ?? ''));
        $this->setSebEnabled((bool)($post['sebEnabled'] ?? ''));
        $this->setOriginalAssessmentId(isset($post['customisedFor']) ? (int)($post['customisedFor'] ) : null);
        $this->setPublishAt(trim($post['publishDate'] ?? ''));
        $this->setOpenAt(trim($post['openDate'] ?? ''));
        $this->setDueAt(trim($post['dueDate'] ?? ''));
        $this->setCloseAt(trim($post['closeDate'] ?? ''));

        if ($assessmentType === AssessmentType::TYPE_LESSON_PLAN) {
            // Work type should always be null for lesson plans.
            $this->setWorkType(null);
        } else {
            try {
                // If an invalid work type has been supplied by the client, treat as information missing.
                $this->setWorkType(WorkTypeMapper::getInstance()->findById($post['workType'])->getId());
            } catch (MapperException) {
                throw $this->errorMapper->generateException(Error\Mapper::ERROR_INFORMATION_MISSING);
            }
        }

        /* Projects need this to instantiate
         */
        $this->setSummator($post['summator'] ?? '');
        $this->setRequireAllActivities((bool)($post['requireAllActivities'] ?? false));

        /* Due Work and Reporting have display vs. instructor marking
         */
        $markType = (int)trim($post['markType'] ?? '');
        $this->setMarkType($markType);

        $instructorMarkType = (int)trim($post['instructor-markType'] ?? '');
        $this->setInstructorMarkType($instructorMarkType);

        $this->setGradingScheme(empty($post['gradingScheme']) ? null : (int)trim($post['gradingScheme']));
        $this->setInstructorGradingScheme(empty($post['instructor-gradingScheme']) ? null : (int)trim($post['instructor-gradingScheme']));

        /* Due Work needs this to instantiate correctly
         */
        $this->setAcceptOnline((int)trim($post['acceptOnline'] ?? ''));
        $this->setUseRubric((bool)($post['useRubric'] ?? ''));
        $this->setSimilarityCheckEnabled(trim($post['similarityCheckEnabled'] ?? '') === 'on');

        /**
         * are we editing? we are only sending through the options when editing / creating. not in the read only course managed state
         */
        if (isset($post['submissionOptions'])) {
            /**
             * new due work checks, this is an array, so we can use frontend validation to confirm if we have selected at least one of these options...
             * firstly clear all the values, and set TRUE to only those that are set.
             */
            $this->setEnableWorksheet(false);
            $this->setEnableOffline(false);
            $this->setEnableText(false);
            $this->setEnableFiles(false);
            foreach ($post['submissionOptions'] as $subOption) {
                $value = trim($subOption ?? '');
                switch ($value) {
                    case 'enableWorksheet':
                        $this->setEnableWorksheet(true);
                        break;
                    case 'enableOffline':
                        $this->setEnableOffline(true);
                        break;
                    case 'enableText':
                        $this->setEnableText(true);
                        break;
                    case 'enableFiles':
                        $this->setEnableFiles(true);
                        break;
                }
            }

            $this->setWorksheetTemplate($post['worksheet'] ?? null);
        }

        $this->setEnablePolicy(trim($post['enablePolicy'] ?? '') === 'on');

        $this->setCapabilityMarkType((int)trim($post['capability-markType'] ?? ''));
        $this->setCapabilityGradeGroup((int)trim($post['capability-gradingScheme'] ?? ''));
        $this->setUseMarkRange(trim($post['useMarkRange'] ?? '') === 'on');

        // Only due works have a max mark.
        if (in_array($assessmentType, [AssessmentType::TYPE_DUE_WORK, AssessmentType::TYPE_REPORTING])) {
            // MaxMark is the maximum available marks for the entire assessment.
            // It is permitted to be null for all mark types except Total Mark.
            // Unfortunately, the due work create form uses these deprecated values, not the new "slug" ones.
            switch ($markType) {
                case MarkType::TYPE_PERCENT:
                case MarkType::TYPE_LETTER:
                case MarkType::TYPE_NONE:
                    $maxMark = null;
                    break;
                default:
                    $maxMark = (float)trim($post['maxMark'] ?? '');
                    break;
            }

            // We should never end up with a 0 max mark unless something goes very wrong, but if that does happen, we want to handle it nicely.
            if ($maxMark === 0.0) {
                throw new UnexpectedValueException('Please provide maximum available marks greater than 0');
            }
            $this->setMaxMark($maxMark);

            // and do it all over again for the instructor mark type

            switch ($instructorMarkType) {
                case MarkType::TYPE_PERCENT:
                case MarkType::TYPE_LETTER:
                case MarkType::TYPE_NONE:
                    $instructorMaxMark = null;
                    break;
                default:
                    $instructorMaxMark = (float)trim($post['instructor-maxMark'] ?? '');
                    break;
            }

            // We should never end up with a 0 max mark unless something goes very wrong, but if that does happen, we want to handle it nicely.
            if ($instructorMaxMark === 0.0) {
                throw new UnexpectedValueException('Please provide maximum available marks greater than 0');
            }
            $this->setInstructorMaxMark($instructorMaxMark);
        }

        if (isset($post['self_reviewable'])) {
            // Value has come though from the form, use that
            $this->setSelfReviewable($post['self_reviewable']);
        } else {
            // Value has not come through from the form (FF turned off) - set to null so that this won't be updated
            $this->setSelfReviewable(null);
        }

        // TODO what should this be?
        if (isset($post['formField'], $post[$post['formField']])) {
            $this->setRubricData($post[$post['formField']]);
        }

        /* Check if any attached files existed in POST data
         * Use their array if it existed
         */
        if (is_array($post['attachment'] ?? null)) {
            $this->setAttachedFiles($post['attachment']);
        }

        /* Task needs this to instantiate correctly
         */
        $this->setHasSelfAssessed(trim($post['hasSelfAssessed'] ?? ''));
        $this->setUrl(trim($post['url'] ?? ''));

        /* Quiz and Dialog Agents share this $post element; same name and same requirements:
         * numeric and no negative values allowed (coerced to zero/unlimited)
         *
         * Duration hours must be greater than or equal to zero
         * Duration minutes must be greater than or equal to zero
         */
        $durationHours = max((int)($post['durationHours'] ?? 0), 0);
        $durationMinutes = max((int)($post['durationMinutes'] ?? 0), 0);

        $this->setDurationHours($durationHours);
        $this->setDurationMinutes($durationMinutes);

        /* Quiz needs this to instantiate correctly
         */
        $this->setLearnosityReference((string)($post['learnosityReference'] ?? ''));
        $this->setReadingTimeDurationHours((int)($post['readingTimeDurationHours'] ?? 0));
        $this->setReadingTimeDurationMinutes((int)($post['readingTimeDurationMinutes'] ?? 0));
        $this->setShuffleItems((int)($post['shuffleItems'] ?? 0));
        $this->setPromptsMode((int)($post['promptsMode'] ?? 0));
        $this->setMaxAttempts((int)($post['maxAttempts'] ?? 0));
        $this->setMarkVisibility((int)($post['visibility'] ?? 0));
        $this->setCompletionMessage($post['completion_message'] ?? '');

        // LTI needs this to instantiate correctly
        $this->setGuid($post['guid'] ?? null);
        $this->setLtiAssessmentUrl($post['ltiAssessmentUrl'] ?? null);
        $this->setLtiAssessmentTitle($post['ltiAssessmentTitle'] ?? null);
        $this->setLtiAssessmentText($post['ltiAssessmentText'] ?? null);
        $this->setLtiToolId(filter_var($post['ltiToolId'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE));

        /* Lesson Plans
         */
        $this->setLessonPlanTemplateId((int)($post['lessonPlanTemplateId'] ?? null));
        $this->setLessonPlanFields($post['field'] ?? []);
        $this->setLessonPlanVisibility('on' === ($post['lessonPlanVisibility'] ?? null) ? \Schoolbox\Role::STUDENT : \Schoolbox\Role::STAFF);
        $this->setLessonPlanDuration((string)($post['duration'] ?? null));

        /* Dialog Agents
         * Note that DurationHours and DurationMinutes is shared with Quiz implementation since it's no longer unique
         * to quiz alone...we're going to run out of space to name things here eventually, unless you start namespacing
         *
            $this->setDurationHours((int)($post['durationHours'] ?? 0));
            $this->setDurationMinutes((int)($post['durationMinutes'] ?? 0));
         */
        $this->setScenarioId((int)($post['scenarioId'] ?? null));

        /* Reporting Activities
         * These are mostly settings for Supporting Data; some parts are shared with Due Work and other activities such
         * as mark types, grade groups, etc. Reporting-specific stuff is namespaced in the ui, unsurprisingly, in a node
         * called "reporting".
         */
        $this->setSupportingDataEnable('on' === ($post['reporting']['supporting']['enable'] ?? null));
        $this->setSupportingDataRange($post['reporting']['supporting']['range'] ?? null);
        $this->setSupportingDataFolders($post['reporting']['supporting']['folders'] ?? null);
        $this->setSupportingDataWorkTypes($post['reporting']['supporting']['workTypes'] ?? null);
        $this->setSupportingDataMegamarkboook('on' === ($post['reporting']['supporting']['megamarkbook'] ?? null));
        $this->setFeedbackCommentsEnable('on' === ($post['reporting']['comments']['enable'] ?? null));

        /* TODO Explain what this thing means: custom fields, line items, what it's supposed to be doing here
         */
        // Ensure custom fields is an array if it's set
        $customFields = $post['customFields'] ?? null;
        if (isset($customFields)) {
            $customFields = json_decode($customFields, true);
            if (!is_array($customFields)) {
                $customFields = null;
            }
        }
        $this->setCustomFields($customFields);
        $this->setLineItemResourceId($post['lineItemResourceId'] ?? null);
        $this->setLineItemScoreMaximum(
            filter_var($post['lineItemScoreMaximum'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
        );
        $this->original = $original;
    }

    /**
     * Produce an assessment
     *
     * Either spawn a new one, or decorate an existing one
     * based on the arg
     *
     * @param IAssessable|null $assessment
     * @return IAssessable
     * @throws MapperException
     * @throws ResourceException
     */
    public function produce(?IAssessable $assessment = null): IAssessable
    {
        if ($assessment instanceof IAssessable) {
            return $this->produceFromExistingAssessment($assessment);
        }

        return $this->produceNew();
    }


    /**
     * Produce object
     *
     * @return IAssessable
     * @throws DomainException
     * @throws MapperException
     * @throws ResourceException
     */
    private function produceNew(): IAssessable
    {
        // Instantiate empty Assessment model
        $assessment = AssessmentType::stringToObject($this->getAssessmentType(), $this->context);

        if ($assessment instanceof Project) {
            $assessment->setSummator(
                (new SummatorFactory($this->getSummator(), $this->getRequireAllActivities()))->produce(),
            );
            $assessment->setRequireAllActivities($this->getRequireAllActivities());
        }

        // Attach context
        $assessment->setContext($this->context);

        return $this->produceFromExistingAssessment($assessment);
    }


    /**
     * Produce object from an existing assessment
     *
     * @param IAssessable $assessment
     * @return IAssessable
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws \RangeException
     * @throws MapperException
     */
    private function produceFromExistingAssessment(IAssessable $assessment): IAssessable
    {
        if ($this->context) {
            // This class property is only set on CREATE, and is not hydrated
            $this->context->setAssessables(new GenericCollection(IAssessable::class, [$assessment]));
        }

        /* Some facts about Course Managed Fields
         */
        $courseManagedFields = new CourseManagedFields($this->creator, $assessment);

        if ($assessment instanceof Project) {
            $decorator = new ProjectDecorator(
                $this->getMarkType(),
                $this->getGradingScheme(),
                $this->getSummator(),
                $this->getRequireAllActivities(),
            );
        } elseif ($assessment instanceof DueWork) {
            /** @var DueWork $originalDueWork */
            $originalDueWork = $this->original;

            /* Pick a decorator since the Course Managed fields should remain unchanged
             * if you're modifying a Due Work in that way
             */
            if ($courseManagedFields->isReadonly()) {
                $decorator = new DueWorkCourseManagedDecorator(
                    $this->getMarkType(),
                    $this->getInstructorMarkType(),
                    $this->getGradingScheme(),
                    $this->getInstructorGradingScheme(),
                    $this->getUseRubric(),
                    $this->getCapabilityMarkType(),
                    $this->getCapabilityGradeGroup(),
                    $this->getUseMarkRange(),
                    $this->getMaxMark(),
                    $this->getInstructorMaxMark(),
                    $this->getRubricData(),
                    $this->getAttachedFiles(),
                    $this->getEnableWorksheet(),
                    $this->getWorksheetTemplate(),
                    $this->getEnableOffline(),
                    $this->getEnableText(),
                    $this->getEnableFiles(),
                    $this->getEnablePolicy(),
                    $originalDueWork
                );
            } else {
                $decorator = new DueWorkDecorator(
                    $this->getAcceptOnline(),
                    $this->getMarkType(),
                    $this->getInstructorMarkType(),
                    $this->getGradingScheme(),
                    $this->getInstructorGradingScheme(),
                    $this->getUseRubric(),
                    $this->getCapabilityMarkType(),
                    $this->getCapabilityGradeGroup(),
                    $this->getUseMarkRange(),
                    $this->getMaxMark(),
                    $this->getInstructorMaxMark(),
                    $this->getRubricData(),
                    $this->getAttachedFiles(),
                    $this->getSelfReviewable(),
                    $this->getSimilarityCheckEnabled(),
                    $this->getEnableWorksheet(),
                    $this->getWorksheetTemplate(),
                    $this->getEnableOffline(),
                    $this->getEnableText(),
                    $this->getEnableFiles(),
                    $this->getEnablePolicy(),
                    $originalDueWork,
                );
            }
        } elseif ($assessment instanceof Task) {
            $decorator = new TaskDecorator(
                $this->getHasSelfAssessed(),
                $this->getUrl(),
                $this->getAttachedFiles()
            );
        } elseif ($assessment instanceof Quiz) {
            $decorator = new QuizDecorator(
                $this->getLearnosityReference(),
                $this->getDurationHours(),
                $this->getDurationMinutes(),
                $this->getReadingTimeDurationHours(),
                $this->getReadingTimeDurationMinutes(),
                $this->getShuffleItems(),
                $this->getPromptsMode(),
                $this->getMaxAttempts(),
                $this->getCompletionMessage(),
                $this->getMarkType(),
                $this->getGradingScheme(),
            );
        } elseif ($assessment instanceof LtiAssessment) {
            $decorator = new LtiDecorator(
                $this->getLtiAssessmentUrl(),
                $this->getLtiAssessmentTitle(),
                $this->getLtiAssessmentText(),
                $this->getLtiToolId(),
                $this->getCustomFields(),
                $this->getLineItemResourceId(),
                $this->getLineItemScoreMaximum(),
                $this->getGuid(),
            );
        } elseif ($assessment instanceof LessonPlan\Model) {
            $decorator = new LessonPlan\Decorator\Decorator(
                $this->getLessonPlanTemplateId(),
                $this->getLessonPlanFields(),
                $this->getLessonPlanVisibility(),
                $this->getAttachedFiles(),
                $this->getLessonPlanDuration(),
            );
        } elseif ($assessment instanceof DialogAgent\Model) {
            $decorator = new DialogAgent\Decorator\Decorator(
                $this->getScenarioId(),
                $this->getDurationHours(),
                $this->getDurationMinutes(),
            );
        } elseif ($assessment instanceof Reporting\Model) {
            $decorator = new Reporting\Decorator\Decorator(

            );
        } else {
            throw new DomainException('Please select an assessment type');
        }

        /* Check required data
         */
        if (trim($this->getTitle()) === '') {
            $typeName = strtolower($assessment->getTypeName());
            throw new InvalidArgumentException("Please set a title for this {$typeName}");
        }

        /* Start manufacturing
         */
        if ($courseManagedFields->isReadonly()) {
            /* Only these fields can be updated by regular guys editing implementations
             */
            $assessment
                ->setDescription($this->getDescription())
                ->setCreator($this->getCreator())
                ->setDefaultOpenSpan($this->getDefaultOpenSpan())
                ->setMarkVisibility($this->getMarkVisibility())
                ->setPublishAt($this->deriveDate($assessment->getBaseContext(), $this->getPublishAt()))
                ->setOpenAt($this->deriveDate($assessment->getBaseContext(), $this->getOpenAt()))
                ->setDueAt($this->deriveDate($assessment->getBaseContext(), $this->getDueAt()))
                ->setCloseAt($this->deriveDate($assessment->getBaseContext(), $this->getCloseAt()))
                ->setCreatedAt(Alaress_Date_Format::now())
                ->setUpdatedAt(Alaress_Date_Format::now());
        } else {
            $assessment
                ->setTitle($this->getTitle())
                ->setDescription($this->getDescription())
                ->setCreator($this->getCreator())
                ->setWeight($this->getWeight())
                ->setDefaultOpenSpan($this->getDefaultOpenSpan())
                ->setCourseManaged($this->getCourseManaged())
                ->setSebEnabled($this->getSebEnabled())
                ->setOriginalAssessmentId($this->getOriginalAssessmentId())
                ->setMarkVisibility($this->getMarkVisibility())
                ->setPublishAt($this->deriveDate($assessment->getBaseContext(), $this->getPublishAt()))
                ->setOpenAt($this->deriveDate($assessment->getBaseContext(), $this->getOpenAt()))
                ->setDueAt($this->deriveDate($assessment->getBaseContext(), $this->getDueAt()))
                ->setCloseAt($this->deriveDate($assessment->getBaseContext(), $this->getCloseAt()))
                ->setCreatedAt(Alaress_Date_Format::now())
                ->setUpdatedAt(Alaress_Date_Format::now());

            if ($this->workType) {
                $assessment->setWorkType(WorkTypeMapper::getInstance()->createModel()->setId($this->workType));
            } else {
                $assessment->setWorkType(null);
            }

            /* Assemble curriculum nodes
             */
            $selectedNodesCsv = $this->getCurriculumNodes();

            if (isset($selectedNodesCsv[0])) {
                $selectedNodes = explode(',', $selectedNodesCsv[0]);

                $assessment->setCurriculumNodes(CurriculumNodeMapper::getInstance()->findByColumn('id', $selectedNodes));
            }
        }

        /* Dialog Agents have a special treatment of dates where Close Date = Due Date
         */
        if ($assessment instanceof DialogAgent\Model) {
            $assessment->setCloseAt($assessment->getDueAt());
        }

        /* Reporting activities have a special treatment of dates where Open Date = Publish Date...also, probably
         * Close Date = Due Date. There isn't really a "late date" for staff to give feedbacks; students never respond
         * to Reporting activities.
         */
        if ($assessment instanceof Reporting\Model) {
            $assessment->setOpenAt($assessment->getPublishAt());
            $assessment->setCloseAt($assessment->getDueAt());
        }

        // find out if the dates are all g - if not, a mage-friendly exception will be thrown
        $assessment->assertValidDates();

        /* Decorate with Assessable-specific data
         */
        $decorator->decorate($assessment);

        return $assessment;
    }


    /**
     * @param AssessmentContext $context
     * @param string            $dateString
     * @return null|Alaress_Date_Format
     */
    private function deriveDate(AssessmentContext $context, $dateString)
    {
        // no dates exist in spec-land
        if ($context instanceof UnitSpecification) {
            return null;
        }

        try {
            // attempt to parse the date
            return Alaress_Date_Format::newLocal($dateString);
        } catch (InvalidArgumentException $e) {
            // unparseable - safely return null
            return null;
        }
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    protected function getAssessmentType()
    {
        return $this->assessmentType;
    }

    /**
     * @param string $assessmentType
     * @return $this
     */
    protected function setAssessmentType($assessmentType)
    {
        $this->assessmentType = $assessmentType;
        return $this;
    }

    /**
     * @return string
     */
    protected function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    protected function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|int
     */
    protected function getWorkType()
    {
        return $this->workType;
    }

    /**
     * @param null|int $workType
     * @return static
     */
    protected function setWorkType($workType)
    {
        $this->workType = $workType;
        return $this;
    }

    /**
     * @return string
     */
    protected function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    protected function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return User
     */
    protected function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param User $creator
     * @return $this
     */
    protected function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * @return float|int
     */
    protected function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param float|int $weight
     * @return $this
     */
    protected function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return float|int
     */
    protected function getDefaultOpenSpan()
    {
        return $this->defaultOpenSpan;
    }

    /**
     * @param float|int $defaultOpenSpan
     * @return $this
     */
    protected function setDefaultOpenSpan($defaultOpenSpan)
    {
        $this->defaultOpenSpan = $defaultOpenSpan;
        return $this;
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
     * @return $this
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

    /**
     * @return IAssessable|null
     */
    public function getOriginalAssessment(): ?IAssessable
    {
        return $this->originalAssessment;
    }

    /**
     * @param int|null $originalAssessmentId
     * @return self
     */
    public function setOriginalAssessmentId(?int $originalAssessmentId): self
    {
        $this->originalAssessmentId = $originalAssessmentId;
        return $this;
    }

    public function setOriginalAssessment(?IAssessable $originalAssessment): self
    {
        $this->originalAssessment = $originalAssessment;
        return $this;
    }

    /**
     * @return string
     */
    protected function getPublishAt()
    {
        return $this->publishAt;
    }

    /**
     * @param string $publishAt
     * @return $this
     */
    protected function setPublishAt($publishAt)
    {
        $this->publishAt = $publishAt;
        return $this;
    }

    /**
     * @return string
     */
    protected function getOpenAt()
    {
        return $this->openAt;
    }

    /**
     * @param string $openAt
     * @return $this
     */
    protected function setOpenAt($openAt)
    {
        $this->openAt = $openAt;
        return $this;
    }

    /**
     * @return string
     */
    protected function getDueAt()
    {
        return $this->dueAt;
    }

    /**
     * @param string $dueAt
     * @return $this
     */
    protected function setDueAt($dueAt)
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    /**
     * @return string
     */
    protected function getCloseAt()
    {
        return $this->closeAt;
    }

    /**
     * @param string $closeAt
     * @return $this
     */
    protected function setCloseAt($closeAt)
    {
        $this->closeAt = $closeAt;
        return $this;
    }

    /**
     * @param AssessmentContext $context
     * @return static
     */
    protected function setContext($context)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return int
     */
    protected function getMarkVisibility()
    {
        return $this->markVisibility;
    }

    /**
     * @param int $markVisibility
     * @return static
     */
    protected function setMarkVisibility($markVisibility)
    {
        $this->markVisibility = $markVisibility;
        return $this;
    }

    /**
     * @return array
     */
    protected function getCurriculumNodes()
    {
        return $this->curriculumNodes;
    }

    /**
     * @param array $curriculumNodes
     * @return $this
     */
    protected function setCurriculumNodes($curriculumNodes)
    {
        $this->curriculumNodes = $curriculumNodes;
        return $this;
    }

    /* Lesson Plan specific
     */

    /**
     * @return string|null
     */
    public function getLessonPlanDuration(): ?string
    {
        return $this->duration;
    }


    /**
     * @param string|null $duration
     * @return $this
     */
    public function setLessonPlanDuration(?string $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    /* Dialog Agent specific
     */

    /**
     * @return int|null
     */
    public function getScenarioId(): ?int
    {
        return $this->scenarioId;
    }


    /**
     * @param int|null $scenarioId
     * @return $this
     */
    public function setScenarioId(?int $scenarioId): self
    {
        $this->scenarioId = $scenarioId;
        return $this;
    }


    /* Reporting activity specific
     */

    /**
     * @return bool|null
     */
    public function getSupportingDataEnable(): ?bool
    {
        return $this->supportingDataEnable;
    }


    /**
     * @param bool|null $supportingDataEnable
     * @return $this
     */
    public function setSupportingDataEnable(?bool $supportingDataEnable): self
    {
        $this->supportingDataEnable = $supportingDataEnable;
        return $this;
    }


    /**
     * @return array|null
     */
    public function getSupportingDataRange(): ?array
    {
        return $this->supportingDataRange;
    }


    /**
     * @param array|null $supportingDataRange
     * @return $this
     */
    public function setSupportingDataRange(?array $supportingDataRange): self
    {
        $this->supportingDataRange = $supportingDataRange;
        return $this;
    }


    /**
     * @return array|null
     */
    public function getSupportingDataCurriculumNodes(): ?array
    {
        return $this->supportingDataCurriculumNodes;
    }


    /**
     * @param array|null $supportingDataCurriculumNodes
     * @return $this
     */
    public function setSupportingDataCurriculumNodes(?array $supportingDataCurriculumNodes): self
    {
        $this->supportingDataCurriculumNodes = $supportingDataCurriculumNodes;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSupportingDataFolders(): ?string
    {
        return $this->supportingDataFolders;
    }


    /**
     * @param string|null $supportingDataFolders
     * @return $this
     */
    public function setSupportingDataFolders(?string $supportingDataFolders): self
    {
        $this->supportingDataFolders = $supportingDataFolders;
        return $this;
    }


    /**
     * @return array|null
     */
    public function getSupportingDataWorkTypes(): ?array
    {
        return $this->supportingDataWorkTypes;
    }


    /**
     * @param array|null $supportingDataWorkTypes
     * @return $this
     */
    public function setSupportingDataWorkTypes(?array $supportingDataWorkTypes): self
    {
        $this->supportingDataWorkTypes = $supportingDataWorkTypes;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getSupportingDataMegamarkboook(): ?bool
    {
        return $this->supportingDataMegamarkboook;
    }


    /**
     * @param bool|null $supportingDataMegamarkboook
     * @return $this
     */
    public function setSupportingDataMegamarkboook(?bool $supportingDataMegamarkboook): self
    {
        $this->supportingDataMegamarkboook = $supportingDataMegamarkboook;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getFeedbackCommentsEnable(): ?bool
    {
        return $this->feedbackCommentsEnable;
    }


    /**
     * @param bool|null $feedbackCommentsEnable
     * @return $this
     */
    public function setFeedbackCommentsEnable(?bool $feedbackCommentsEnable): self
    {
        $this->feedbackCommentsEnable = $feedbackCommentsEnable;
        return $this;
    }
}
