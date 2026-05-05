<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model as CriteriaModel;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as DialogAgentScenarioMapper;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Application\Exception\Misconfigured;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\CompositeFactoryFactory;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Mapper as DialogAgentResponseMessageMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Translator\ModelToArray as DialogAgentResponseMessageTranslator;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Timeline\Full as FullTimeline;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\AbstractUnit;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Learning\DialogAgent\ValidDialogAgentEnabled;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Error\Error;

/**
 * This fulfils the standard screens enforced in all learning activities via the \Schoolbox\Assessment\Controller.
 * These include the summary, status, response creation screens.
 *
 * Anything outside of that set typically end up in their own controller \Schoolbox\Learning\*\Controller elsewhere
 * to break away from the inheritance tree here.
 *
 * Whether or not that's a great idea is yet to be determined; generally inheritance isn't a good move if you can
 * recompose your objects to avoid it. Sadly we made this mistake so long ago and consolidated upon it.
 *
 * An alternative to putting your activity-specific methods in \Schoolbox\Learning\*\Controller would be to simply
 * dump them into the relevant subclass of \Schoolbox\Assessment\Controller to inherit its superclass' properties,
 * methods, and modcons. Both seem acceptable: the latter marries you into the inheritance tree, but splitting away
 * means you end up duping a lot of perms-based code which is kind of disgusting. You lose either way :(
 */
class Controller extends Assessment\Controller
{
    /**
     * Constructor.
     *
     * @param UserMapper $userMapper
     * @param ErrorMapper $errorMapper
     * @param AssessmentMapperProxy $assessmentMapper
     * @param WorkTypeMapper $workTypeMapper
     * @param Access $access
     * @param Transactor $transactor
     * @param BreadcrumbFactory $bakery
     * @param \Schoolbox\Assessment\Rubric\DataModel\Result\Mapper $resultSetMapper
     * @param RubricDataSource $rubricMapper
     * @param Mapper $termMapper
     * @param DataSource $participantDataSource
     * @param CourseSpecification\Mapper $courseSpecificationMapper
     * @param UnitSpecification\Mapper $unitSpecificationMapper
     * @param RosettaStone $rosettaStone
     * @param Replicator $replicator
     * @param LatestViewMapper $latestViewMapper
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper $lessonPlanDataModelMapper
     * @param DialogAgentDataModelMapper $dialogAgentDataModelMapper
     * @param DialogAgentScenarioMapper $dialogAgentScenarioMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
     * @param DialogAgentResponseMessageMapper $dialogAgentResponseMessageMapper
     * @param IdFinder $filtersAssessmentIdFinder
     * @param CompositeFactoryFactory $filtersFactoryFactory
     * @param Filter\Mapper $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param CourseImplementation\Mapper $courseImplementationMapper
     * @param UnitImplementation\Mapper $unitImplementationMapper
     * @param EditorTemplateHelper $editorTemplateHelper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        protected \Schoolbox\Assessment\Rubric\DataModel\Result\Mapper $resultSetMapper,
        protected \Schoolbox\Assessment\Rubric\DataModel\DataSource $rubricMapper,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecification\Mapper $courseSpecificationMapper,
        UnitSpecification\Mapper $unitSpecificationMapper,
        RosettaStone $rosettaStone,
        Replicator $replicator,
        LatestViewMapper $latestViewMapper,
        Project\ResultCache\Mapper $projectResultCacheMapper,
        LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        protected DialogAgentDataModelMapper $dialogAgentDataModelMapper,
        protected DialogAgentScenarioMapper $dialogAgentScenarioMapper,
        private DialogAgentResponseMapper $dialogAgentResponseMapper,
        private DialogAgentResponseMessageMapper $dialogAgentResponseMessageMapper,
        protected Filter\Filters\Assessment\IdFinder $filtersAssessmentIdFinder,
        protected Filter\CompositeFactoryFactory $filtersFactoryFactory,
        protected Filter\Mapper $filterMapper,
        protected Filter\Selection\Mapper $filterSelectionMapper,
        private CourseImplementation\Mapper $courseImplementationMapper,
        private UnitImplementation\Mapper $unitImplementationMapper,
        private EditorTemplateHelper $editorTemplateHelper
    ) {
        parent::__construct(
            $userMapper,
            $errorMapper,
            $assessmentMapper,
            $workTypeMapper,
            $access,
            $transactor,
            $bakery,
            $resultSetMapper,
            $rubricMapper,
            $termMapper,
            $participantDataSource,
            $courseSpecificationMapper,
            $unitSpecificationMapper,
            $rosettaStone,
            $replicator,
            $latestViewMapper,
            $projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper,
            $dialogAgentScenarioMapper,
            $filtersAssessmentIdFinder,
            $filtersFactoryFactory,
            $filterMapper,
            $filterSelectionMapper,
            $courseImplementationMapper,
            $unitImplementationMapper,
            editorTemplateHelper: $editorTemplateHelper
        );
    }


    /**
     * Assessment Summary Screen for a Dialog Agent
     *
     * @param Model|IAssessable $assessment
     * @param User              $student
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSummary(Model|IAssessable $assessment, User $student): void
    {
        /** @var Model $assessment */
        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder());

        /* Craft a returnUrl, to redirect back to the place from whence you came
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        /**
         * record view
         */
        $lastView = new Assessment\Views\LatestView(
            $this->getAccess(),
            $this->latestViewMapper,
            new \Schoolbox\External\User(
                $this->getConfigLoader()->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getUserMapper()
            )
        );

        $lastView->addNewView($assessment, $student, $this->getUser());

        /**
         * get list of all activities within this course
         * only for staff
         */
        $unitAssessmentsTranslator = new Assessment\Translator\UnitAssessments(
            $this->routeGenerator,
            $this->getAuthorizer(),
            $student,
            $this->getUser(),
            true
        );

        $courseAssessments = [];

        if ($this->getUser()->getRole()->isStaff()) {
            // if we are inside a project get the projects context
            $course = null;

            if ($assessment->getContext()->isImplementationContext()) {
                //get all courses for the Class Page (Implementation)...
                $folder = $assessment->getContext()->getFolder();
                $courses = $this->courseImplementationMapper->findByFolderId($folder->getId());

                foreach ($courses as $course) {
                    // if the course has been deleted, ignore
                    if ($course->isDeleted()) {
                        continue;
                    }

                    $course->setUnits($this->unitImplementationMapper->findAllByCourseId($course->getId()));

                    foreach ($course->getUnits() as $unitImpl) {
                        /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $unitImpl */
                        $unitImplId  = $unitImpl->getId();
                        $options     = ['order' => ['sequence' => 'ASC']];

                        $assessments = $this->getAssessmentMapper()->findAllByUnitImplementationIds([$unitImplId], $options)
                            // Remove Lesson plans, no linking to other lessons
                            ->filter(function ($assessment) {
                                /** @var IAssessable $assessment */
                                return !($assessment instanceof Model);
                            });

                        // hydrate participation
                        $this->getAssessmentMapper()->loadParticipantsIntoAssessmentCollection($assessments);

                        $unitImpl->setAssessables($assessments);

                        $courseAssessments['units'][] = [
                            'id'                         => $unitImplId,
                            'title'                      => $unitImpl->getTitle(),
                            'closeDate'                  => $unitImpl->getDueDate()?->getUsingFormat(DATE_ATOM),
                            'closeDateFormatted'         => $unitImpl->getDueDate()?->getRelativeTime(),
                            'openDate'                   => $unitImpl->getOpenDate()?->getUsingFormat(DATE_ATOM),
                            'openDateFormatted'          => $unitImpl->getOpenDate()?->getRelativeTime(),
                            'relativeCloseDateFormatted' => $unitImpl->getRelativeDueDate()->getRelativeTime(),
                            'relativeOpenDateFormatted'  => $unitImpl->getRelativeOpenDate()->getRelativeTime(),
                            'assessments'                => $assessments->reindex()->map($unitAssessmentsTranslator)
                        ];
                    }
                }
            } else {
                $unit = $assessment->getContext()->getBaseContext();

                if ($unit instanceof AbstractUnit) {
                    $course = $unit->getCourse();
                }

                $course->setUnits($this->unitSpecificationMapper->findAllByCourseId($course->getId()));

                foreach ($course->getUnits() as $unitSpec) {
                    /** @var \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $unitSpec */
                    $unitSpecId = $unitSpec->getId();
                    $options = ['order' => ['sequence' => 'ASC']];

                    $assessments = $this->getAssessmentMapper()->findAllByUnitSpecificationId($unitSpecId, $options)
                        // Remove Lesson plans, no linking to other lessons
                        ->filter(function ($assessment) {
                            /** @var IAssessable $assessment */
                            return !($assessment instanceof Model);
                        });

                    $unitSpec->setAssessables($assessments);

                    $courseAssessments['units'][] = [
                        'id' => $unitSpecId,
                        'title' => $unitSpec->getTitle(),
                        'closeDate' => null, // No Close Date for specifications
                        'closeDateFormatted' => null, // No Close Date for specifications
                        'openDate' => null, // No Open Date for specifications
                        'openDateFormatted' => null, // No Open Date for specifications
                        'relativeCloseDateFormatted' => null, // No Close Date for specifications
                        'relativeOpenDateFormatted' => null, // No Open Date for specifications
                        'assessments' => $assessments->reindex()->map($unitAssessmentsTranslator)
                    ];
                }
            }
        }

        $participant = $assessment->getParticipantIfExtant($student);

        /* Find out if there's an unsubmitted dialog agent linked to a session (Resume) and create a
         * button which goes to that particular response so students can continue it.
         *
         * Otherwise create a new response.
         */
        $existingResponse = null;

        if ($participant) {
            $pb = $this->dialogAgentResponseMapper->getPredicateBuilder()
                ->equals('dialog_agent_id', $this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId())->getId())
                ->equals('learner_id', $participant->getUser()->getId())
                ->isNull('submitted_by')
                ->isNull('submitted_at');

            $existingResponse = $this->dialogAgentResponseMapper->findByPredicates($pb)->first();
        }

        $twigData = [
            'lastViews'         => $lastView->getTwigViews($assessment, $student, $isViewerAnInstructor),
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'assessment'        => $assessment,
            'student'           => $student,
            'returnUrl'         => $returnUrl,
            'returnUrlEncoded'  => Redirect::encode($returnUrl),
            'courseAssessments' => $courseAssessments,
            'participant'       => $participant,
            /* An array representing a call to action
             */
            'button' => (new Button($this->routeGenerator, $this->authorizer, $this->getAccess()))->generate($assessment, $participant, $this->getUser()),
            /* An unsubmitted response that can be resumed
             */
            'existingResponse' => $existingResponse,
            /* The full history of interactions for this dialog agent
             * Note that we won't have feedbacks in this
             */
            'timeline' => $participant ? (new FullTimeline($participant))->getForUser($this->getUser()) : null,

            /* FIXME Controversial:
             *  whether "staff-only fields" really means "instructor-only fields",
             *  or whether it's strictly for the Role::STAFF demographic? Non-staff
             *  roletypes can end up fulfilling Instructor responsibilities
             */
            'isInstructor' => $isViewerAnInstructor,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/summary/student.twig',
            $twigData
        );
    }


    /**
     * This is where students will create or resume their dialog agent discussion stuffs
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        /** @var Model $assessment */

        /**
         * Due to the swamp, we can't make the canonical check in the model (canLearnerCreateResponse)
         * use configLoader to check the AI config for whether this should be possible.
         * To avoid an instanceof in the module, we've decided to do a simple check here instead.
         *
         * Ending up here is only possible if dialog agent was once enabled, an activity was created,
         * dialog agent was then disabled, and then a student tries to get here before the activity closes
         */
        if (!new ValidDialogAgentEnabled($this->getConfigLoader())->isSatisfied()) {
            throw new Misconfigured("{$assessment->getTitle()} is not available, contact your system administrator");
        }

        $participant = $assessment->getParticipantIfExtant($student);

        // Is the page being displayed in readonly mode?
        /* TODO Are students allowed to visit this after the activity is closed?
         * I don't think you should be able to get here if it's locked. go to the view page instead...
         */
        // $isInteractionLocked = (new LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment);

        /* Prepare a redirection url
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        /**
         * If there is an existing unsubmitted response and resume it if there is one,
         * otherwise create a new response for the student to create messages against
         * @var Response\Model|null $response
         */
        $response = $participant?->findMostRecentDraftResponse();
        // TODO: is this endpoint supposed to be able to load the existing auth response, and then deny you from messaging if its submitted?
        if (null === $response) {
            $response = $this->dialogAgentResponseMapper->createModel()
                ->setAssessable($assessment)
                ->setSubmittedOnBehalfOf($student)
                ->setCreatedBy($this->getUser()->getId())
                ->setCreatedAt(\Alaress_Date_Format::now())
                ->setUpdatedBy($this->getUser()->getId())
                ->setUpdatedAt(\Alaress_Date_Format::now());

            // TODO: deal with existing auth responses that were submitted... and consider the workflows to end up here
            $response->setAuthoritative(true);

            $this->dialogAgentResponseMapper->save($response);
        }

        /**
         * Build up the existing feed from the logs.
         * Also add extra messages such as the opening message as if they were real messages if available
         */
        $feed = new \Schoolbox\Collection();
        $feed->add([
            'type' => 'response',
            'data' => [
                'status' => 'success',
                'responseId' => null,
                'image' => null,
                'message' => \Scrub::markdown($assessment->getOpeningMessage()),
            ],
            'createdAt' => $assessment->getCreatedAt(),
        ]);

        $responseMessageTranslator = new DialogAgentResponseMessageTranslator();
        $responseMessages = $this->dialogAgentResponseMessageMapper->findForResponse($response->getId());
        foreach ($responseMessages as $message) {
            $feed->add($responseMessageTranslator->__invoke($message));
        }

        $userTranslator = new User\Translator\Author\ModelToArray();
        $responseLinksTranslator = new Response\Translator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $responseTranslator = new Response\Translator\ModelToArray($responseLinksTranslator);

        $data = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([
                \sprintf('Create Response for %s', $assessment->getTitle()),
            ]),
            'assessment' => $assessment,
            'feed' => $feed,
            'studentData' => $userTranslator->__invoke($student),
            'responseData' => $responseTranslator->__invoke($response),
            'duration' => $assessment->isDurationUnlimited() ? null : $assessment->getDuration(),
            'returnUrl' => $returnUrl,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/response/chat.twig',
            $data
        );
    }


    /**
     * In dialog agent land, a response must already exist to submit against it,
     * since the logic is identical to SOBO, this is done inside Learning\DialogAgent\Controller instead
     * to take advantage of the auth rules in that module.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws Exception\NotFound
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // this page does not exist
        throw new Exception\NotFound();
    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @param bool        $isCreate
     * @param bool        $toParticipation
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false, bool $isCreate = false, bool $toParticipation = false): string
    {
        /**
         * Always go to the summary screen when a dialog agent has been created,
         * but send the return url with it
         *
         * If the dialog agent was only updated, use the parent logic to determine the redirect instead
         */
        if ($isCreate) {
            $redirect = $this->routeGenerator->generate('learningDialogAgent.getUpdateForm', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $this->getUser()->getId()
            ]);

            if ($postReturnUrl) {
                $redirect .= '?' . Redirect::RETURN_URL . "=$postReturnUrl";
            }

            return $redirect;
        }

        return parent::determineRedirect($assessment, $postReturnUrl, $defaultReturnUrl, $relocated, $isCreate);
    }


    /**
     * @inheritDoc
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        return [
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];
    }


    /**
     * @inheritDoc
     *
     * @param IAssessable|Model $assessment
     * @return void
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     */
    public function getStatus(IAssessable|Model $assessment): void
    {
        /* Yank out scenario fields
         */
        /** @var Model $assessment */
        $scenario = $this->dialogAgentScenarioMapper->findById($assessment->getScenarioId());

        /** All implementations if $assessment is a spec, or $assessment itself if it's already one */
        $implementations = new \Schoolbox\GenericCollection(Model::class);

        // impl assessments we can just grab the details, spec we need to get the unit assessments first
        if ($assessment->isImplementation()) {
            $implementations->add($assessment);
        } else {
            foreach ($this->getUnitAssessments($assessment) as $unitAssessment) {
                /** @var Model $unitAssessment */
                $implementations->add($unitAssessment);
            }
        }

        $userTranslator = new \Schoolbox\User\Translator\ModelToArray();
        $responseLinksTranslator = new Response\Translator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $responseTranslator = new Response\Translator\ModelToArray($responseLinksTranslator);

        $assessmentsPayload = [];
        foreach ($implementations as $implementation) {
            $participantPayload = [];

            foreach ($implementation->getAllParticipants() as $participant) {
                /** @var Participant $participant */

                /* Are they excluded? ignore them */
                if ($participant->isExcluded()) {
                    continue;
                }

                $authoritativeResponse = null;

                if ($participant->getAllResponses()->count()) {
                    /** @var Response\Model|null $authoritativeResponse */
                    $authoritativeResponse = $implementation->getAuthoritativeResponse($participant, true);
                }

                /* This would have been populated via the AI
                 */
                $authoritativeFeedback = null;

                if ($participant->getAllFeedbacks()->count()) {
                    $authoritativeFeedback = $implementation->getAuthoritativeFeedback($participant, true);
                }

                /* The frontend expects a {} for an empty record, so use a stdClass */
                $feedback = new \stdClass();

                /* An authoritative response must exist;
                 * it must be in Submitted state;
                 * there must be an authoritative feedback in existence
                 *
                 * ...at which point you can display it on the status screen.
                 */
                if ($authoritativeResponse !== null && null !== $authoritativeResponse->getSubmittedAt() && $authoritativeFeedback) { // also maybe if it's been submitted?
                    foreach ($authoritativeFeedback->getCriteria() as $feedbackCriterion) {
                        $feedback->{$feedbackCriterion->getScenarioCriterionId()} = $feedbackCriterion->getBody();
                    }
                }

                $routeParams = [
                    'assessment'   => $implementation,
                    'assessmentId' => $implementation->getId(),
                    'student'      => $participant->getUser(),
                    'studentId'    => $participant->getUser()->getId(),
                ];

                $participantPayload[] = [
                    'id' => $participant->getUser()->getId(),
                    'user' => $userTranslator->convertLinkedUser($participant->getUser()),
                    'response' => $authoritativeResponse ? $responseTranslator->__invoke($authoritativeResponse) : null,
                    'feedback' => $feedback,
                    '_routes' => [
                        'summary' => $this->getRouteIfAuthorized('learningAssessment.getSummary', $routeParams),
                    ],
                ];
            }

            $assessmentsPayload[] = [
                'id' => $implementation->getId(),
                'folder' => [
                    'id' => $implementation->getBaseContext()->getFolder()->getId(),
                    'title' => $implementation->getBaseContext()->getFolder()->getTitle(),
                ],
                'participants' => $participantPayload,
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/status.twig',
            [
                'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                    [
                        sprintf('Status for %s', $assessment->getTitle()),
                    ]
                ),
                'assessment' => $assessment,
                'criteria'   => $scenario->getCriteria()->map(fn(CriteriaModel $criterion) => [
                    'id' => $criterion->getId(),
                    'title' => $criterion->getTitle(),
                ]),
                'assessments' => $assessmentsPayload,
            ]
        );
    }
}
