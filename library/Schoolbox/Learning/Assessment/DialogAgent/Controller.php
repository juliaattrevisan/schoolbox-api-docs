<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as DialogAgentScenarioMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\LockedChecker;
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
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Error\Error;

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
     * @param DialogAgentDataModelMapper $dialogAgentMapper
     * @param DialogAgentScenarioMapper $dialogAgentScenarioMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
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

        /* Decide if this $student can create a response to this learning activity
         */
        $canRespond = false;

        try {
            $assessment->assertCanRespond($participant);
            $canRespond = true;
        } catch (\DomainException $e) {
            // noop
        }

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

            $existingResponse = $this->dialogAgentResponseMapper->findByPredicates($pb);
        }

        /* The total time spent in this learning activity must not exceed the total allowed duration,
         * if set; not sure how we're gonna calculate that atm
         *
         * TODO figure out how to aggregate total time spent in a chat
         *  $assessment->assertDuration($participant); ...?
         */

        $twigData = [
            'lastViews'         => $lastView->getTwigViews($assessment, $student, $isViewerAnInstructor),
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'assessment'        => $assessment,
            'student'           => $student,
            'returnUrl'         => $returnUrl,
            'returnUrlEncoded'  => Redirect::encode($returnUrl),
            'courseAssessments' => $courseAssessments,
            'participant'       => $participant,
            'canRespond'        => $canRespond,
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
     * This is where students will create their dialog agent discussion stuffs
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws Error
     * @throws RouteNotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        /** @var null|Participant $participant */
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

            $existingResponse = $this->dialogAgentResponseMapper->findByPredicates($pb);
        }

        // Is the page being displayed in readonly mode?
        /* TODO Are students allowed to visit this after the activity is closed?
         */
        $isInteractionLocked = (new LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment);

        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);

        /* Prepare a redirection url
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        $data = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    sprintf('Create Response for %s', $assessment->getTitle()),
                ]
            ),
            'assessment' => $assessment,
            'student' => $student,
            'participant' => $participant,
            'existingResponse' => $existingResponse,
            'errorMessage' => $errorMessage,
            'readonly' => $isInteractionLocked,
            'returnUrl' => $returnUrl,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/response/create.twig',
            $data
        );
    }


    /**
     * Resume an unsubmitted response
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     */
    public function getResumeResponse(IAssessable $assessment, User $student): void
    {
        /** @var null|Participant $participant */
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

            $existingResponse = $this->dialogAgentResponseMapper->findByPredicates($pb);
        }

        // Is the page being displayed in readonly mode?
        /* TODO Are students allowed to visit this after the activity is closed?
         */
        $isInteractionLocked = (new LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment);

        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);

        /* Prepare a redirection url
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }
        $data = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    sprintf('Create response for %s', $assessment->getTitle()),
                ]
            ),
            'assessment' => $assessment,
            'student' => $student,
            'participant' => $participant,
            'existingResponse' => $existingResponse,
            'errorMessage' => $errorMessage,
            'readonly' => $isInteractionLocked,
            'returnUrl' => $returnUrl,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/response/create.twig',
            $data
        );
    }


    /**
     * TODO This is when students hit the submit button to finalise their dialog agent discussion
     *  Currently a placeholder to spawn data
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        $requestPost = $this->requestPost;

        /* If an existing responseId was provided, update that instance
         * Otherwise spawn a new instance, configure that, and save it.
         */
        if ($requestPost->get('responseId')) {
            $response = $this->dialogAgentResponseMapper->findById($requestPost->get('responseId'));
        } else {
            $response = $this->dialogAgentResponseMapper->createModel()
                ->setCreatedAt(\Alaress_Date_Format::now())
                ->setUpdatedAt(\Alaress_Date_Format::now());
        }

        $response->setAssessable($assessment);

        $this->dialogAgentResponseMapper->save($response);

        /* TODO this probably isn't tne nicest way to generate a redirected url
         *  There *has* to be a cleaner way than this, esp. since returnUrl is a const in the Redirect
         *  class itself, but is magically being written here.
         */
        $this->response = $this->redirectFound(
            $this->routeGenerator->generate(
                'learningAssessment.getSummary',
                [
                    'assessmentId' => $assessment->getId(),
                    'studentId'    => $student->getId()
                ]
            ) . (
            ($requestPost->get('returnUrl') === null) ? '' :
                '?returnUrl=' .  Redirect::encode($requestPost->get('returnUrl'))
            )
        );
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
            $redirect = $this->routeGenerator->generate('learningAssessment.getSummary', [
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
        /* TODO Pretty sure there are some conditionals about when someone can make a Dialog Agent
         *  response such as the Duration hasn't elapsed; that it's published+open; etc.
         *  Not sure atm if that idea overlaps whatever this meant
         */
        return [
            /* This is only used when creating assessments outside the Course Import workflow.
             */
            'acceptingResponses' => true,
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];
    }


    /**
     * @inheritDoc
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function getStatus(IAssessable $assessment): void
    {
        /* TODO this is probably gonna be your strategic view of people doing a dialog agent activity
         *  so you can see who requires assistance. It'll need to poll to update periodically.
         */
    }
}
