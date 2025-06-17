<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

use Alaress_Date_Format;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Error;
use Schoolbox\Error\Mapper;
use Schoolbox\Instance;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\Provider as LtiTool;
use Schoolbox\LTI\ResourceLink\LaunchFactory;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Permission\Access\Access;
use Schoolbox\TokenReplacer;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Assessment\Controller
{
    /**
     * Schoolbox's registry of LTI providers (a.k.a. tools).
     */
    private LtiTool\Mapper $ltiToolMapper;

    /**
     * Schoolbox's LTI assessment mapper, for the purpose of testing the
     * creation of scores directly within Schoolbox (instead of listening for
     * the score publish service).
     */
    private LtiAssessment\Mapper $ltiAssessmentMapper;

    /**
     * Schoolbox's LTI assessment score mapper, for the purpose of testing the
     * creation of scores directly within Schoolbox (instead of listening for
     * the score publish service).
     */
    private LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper;

    /**
     * Repository of tool+platform registrations.
     */
    private RegistrationRepositoryInterface $registrations;

    /**
     * Instance data repository.
     */
    private Instance $instance;

    /**
     * User codec, used to encode a user into a login hint and in the score
     * repository.
     */
    private UserCodec $userCodec;


    /**
     * Constructor
     *
     * @param UserMapper                            $userMapper
     * @param Mapper                                $errorMapper
     * @param MapperProxy                           $assessmentMapper
     * @param WorkTypeMapper                        $workTypeMapper
     * @param Access                                $access
     * @param Transactor                            $transactor
     * @param Factory                               $bakery
     * @param Term\Mapper                           $termMapper
     * @param DataSource                            $participantDataSource
     * @param CourseSpecificationMapper             $courseSpecificationMapper
     * @param UnitSpecificationMapper               $unitSpecificationMapper
     * @param RosettaStone                          $rosettaStone
     * @param Replicator                            $replicator
     * @param LtiTool\Mapper                        $ltiToolMapper
     * @param LtiAssessment\Mapper                  $ltiAssessmentMapper
     * @param LtiAssessmentScore\Mapper             $ltiAssessmentScoreMapper
     * @param RegistrationRepositoryInterface       $registrations
     * @param Instance                              $instance
     * @param UserCodec                             $userCodec
     * @param LatestViewMapper                      $latestViewMapper
     * @param Assessment\Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper              $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper             $lessonPlanDataModelMapper
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        RosettaStone $rosettaStone,
        Replicator $replicator,
        LtiTool\Mapper $ltiToolMapper,
        LtiAssessment\Mapper $ltiAssessmentMapper,
        LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper,
        RegistrationRepositoryInterface $registrations,
        Instance $instance,
        UserCodec $userCodec,
        LatestViewMapper $latestViewMapper,
        protected Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        protected LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        protected LessonPlanDataModelMapper $lessonPlanDataModelMapper
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
            $this->projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper
        );
        $this->ltiToolMapper            = $ltiToolMapper;
        $this->ltiAssessmentMapper      = $ltiAssessmentMapper;
        $this->ltiAssessmentScoreMapper = $ltiAssessmentScoreMapper;
        $this->registrations            = $registrations;
        $this->instance                 = $instance;
        $this->userCodec                = $userCodec;
    }

    /**
     * Fill in Twig data required for rendering the create LTI assessment form.
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        return [
            // The message handler only accepts deep linking responses
            'deepLinkingResponseMessageType' => LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_RESPONSE,
            'acceptingResponses'             => true,
            'ltiTools'                       => $this->ltiToolMapper->findAllAvailableForAssessment()
                                                                    ->sortNaturally('name'),
        ];
    }

    /**
     * Placeholder for progress summary for all students on an LTI assessment.
     *
     * @param IAssessable|Model $assessment
     * @param bool $courseView
     * @api GET /learning/assessment/:assessmentId/status
     * @since 21.1.0
     *
     */
    public function getStatus(IAssessable $assessment, bool $courseView = false)
    {
        $twigData = [
            'type'        => Assessment\AssessmentType::objectToString($assessment),
            'assessment'  => $assessment,
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), '' ]),
            'message'      => strip_tags($this->requestQuery->get('lti_msg', '')),
            'errorMessage' => strip_tags($this->requestQuery->get('lti_errormsg', '')),
        ];

        // shortcut if course status view, as we are building the twig elsewhere...
        if ($courseView) {
            return $twigData;
        }

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lti/status.twig',
            $twigData,
            "Status of {$assessment->getTitle()}",
            'submission'
        );
    }

    /**
     * Display a summary of a student's progress on an LTI assessment.
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 21.1.0
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        // Check SEB requirement
        $sebDownload = $this->getRouteIfAuthorized(
            'learningAssessment.getSebDownload',
            [
                'assessment'   => $assessment,
                'assessmentId' => $assessment->getId(),
            ]
        );
        if ($sebDownload && !$this->isSebCheckPasses($assessment)) {
            $this->response = $this->redirectFound($sebDownload);
            return;
        }

        // Display a timeline for the student's participation
        $participant = $assessment->getParticipantIfExtant($student);
        $timeline    = null;
        if ($participant) {
            $timeline = (new Assessment\Timeline\Generic($participant))->getForUser($this->getUser());
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

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lti/summary.twig',
            [
                'lastViews'    => $lastView->getTwigViews($assessment, $student, $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder())),
                'breadcrumbs'  => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                                               ->add([ $assessment->getTitle(), '' ]),
                'assessment'   => $assessment,
                'participant'  => $participant,
                'student'      => $student,
                'timeline'     => $timeline,
                'returnUrl'    => $this->requestQuery->get(Assessment\Redirect::RETURN_URL),
                'message'      => strip_tags($this->requestQuery->get('lti_msg', '')),
                'errorMessage' => strip_tags($this->requestQuery->get('lti_errormsg', '')),
            ],
            "Status of {$assessment->getTitle()}",
            'submission'
        );
    }

    /**
     * Student's won't create responses to LTI assessments directly: they will
     * be created via the LTI tool publishing a score to Schoolbox.
     *
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        throw new NotFound();
    }

    /**
     * Student's won't create responses to LTI assessments directly: they will
     * be created via the LTI tool publishing a score to Schoolbox.
     *
     * However: for testing purposes only it is possible to create either a
     * response or feedback from the status page.
     *
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // Replace any tokens in the comment with the relevant details
        $comment = (new TokenReplacer\Student(new TokenReplacer\Simple()))
            ->replace(trim((string)$this->requestPost->get('comment')), $student);

        $scoreModel = $this->ltiAssessmentScoreMapper->createModel()
            ->setStudent($student)
            ->setIsResponse($this->requestPost->getBool('isResponse', false))
            ->setIsFeedback($this->requestPost->getBool('isFeedback', false))
            ->setScoreGiven(
                filter_var($this->requestPost->get('scoreGiven'), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            )
            ->setScoreMaximum(
                filter_var($this->requestPost->get('scoreMaximum'), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            )
            ->setComment($comment)
            ->setSubmittedAt(Alaress_Date_Format::now());

        // Set activity/grading progress dependent on if response/feedback
        $scoreModel = $scoreModel
            ->setActivityProgress(
                $scoreModel->getIsResponse()
                    ? LtiAssessmentScore\Model::ACTIVITY_PROGRESS_SUBMITTED
                    : LtiAssessmentScore\Model::ACTIVITY_PROGRESS_INITIALIZED
            )
            ->setGradingProgress(
                $scoreModel->getIsFeedback()
                    ? LtiAssessmentScore\Model::GRADING_PROGRESS_FULLY_GRADED
                    : LtiAssessmentScore\Model::GRADING_PROGRESS_NOT_READY
            );

        // Set LTI assessment: need to get it out of the LTI assessment itself
        $scoreModel = $scoreModel
            ->setLtiAssessment(
                $this->ltiAssessmentMapper->findOneByColumn('assessment_id', $assessment->getId())
            );

        // Set marking user (may be anonymous)
        $markingUserId = $this->requestPost->getInt('markingUser');
        $scoreModel->setMarkingUser(
            $markingUserId
                ? (new User())->setId($markingUserId)
                : null
        );

        // Save
        $this->ltiAssessmentScoreMapper->save($scoreModel);

        // Is feedback? Update project if so
        if ($scoreModel->getIsFeedback()) {
            (new Assessment\Project\ResultCache\ResultCache($this->projectResultCacheMapper))
                ->refresh($this->getAssessmentMapper(), $assessment, $student);
        }

        // Then redir to the return URL, or the assessment's folder
        $encodedReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        $this->response = $this->redirectAfterPost(
            isset($encodedReturnUrl)
                ? Redirect::decode($encodedReturnUrl)
                : ('/homepage' . $assessment->getBaseContext()->getFolder()->getId()),
        );
    }

    /**
     * Opens a page which launches the LTI assessment's resource link, within
     * the context of an instructor.
     * (This should be the status page for the assessment within the LTI tool.)
     *
     * The launch method depends on the assessment's LTI tool's target:
     * * iframe: launch within an iframe
     * * window: redirect directly to the launch
     *
     * @api   GET /learning/assessments/:assessmentId/lti/status
     * @since 22.0.0
     *
     * @param Model $assessment
     */
    public function getLtiStatusLaunch(Model $assessment): void
    {
        // Just redirect to the tool if opening in a new window
        if ($assessment->getLtiTool()->isTargetWindow()) {
            $this->getLaunchAssessmentAsInstructor($assessment);
            return;
        }

        // Show the tool in an iframe
        $breadcrumbs = $this->getBakery()
            ->produceFromAssessment($assessment, $this->getUser())
            ->add([
                $assessment->getTitle(),
                $this->getRouteIfAuthorized(
                    'learningAssessment.getStatus',
                    [
                        'assessmentId' => $assessment->getId(),
                        'assessment'   => $assessment,
                    ]
                )
            ])
            ->add(['View in LTI Tool', '']);
        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lti/ltiStatus.twig',
            [
                'assessment'  => $assessment,
                'breadcrumbs' => $breadcrumbs,
            ]
        );
    }

    /**
     * Launches this LTI assessment's resource link, within the context of an
     * instructor.
     *
     * @api   GET /learning/assessments/:assessmentId/lti/launch/instructor
     * @since 21.1.0
     *
     * @param Model $assessment
     */
    public function getLaunchAssessmentAsInstructor(Model $assessment): void
    {
        $message = $this->buildLaunchResourceLinkMessage($assessment, $this->getUser(), $this->getUser());

        $this->setAllowBackToTheFuture(false);

        // And send message to template, where it's POSTed
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningAssessment/lti/messageForm.twig',
            ['message' => $message]
        );
    }

    /**
     * Opens a page which launches the LTI assessment's resource link, within
     * the context of a student.
     * (This should be the student's create response page for the assessment
     *  within the LTI tool.)
     *
     * The launch method depends on the assessment's LTI tool's target:
     * * iframe: launch within an iframe
     * * window: redirect directly to the launch
     *
     * @param Model $assessment
     * @param User  $student
     *@api   GET /learning/assessments/:assessmentId/:studentId/lti/submit
     * @since 22.0.0
     *
     */
    public function getLtiCreateResponseLaunch(Model $assessment, User $student): void
    {
        // Just redirect to the tool if opening in a new window
        if ($assessment->getLtiTool()->isTargetWindow()) {
            $this->getLaunchAssessmentAsStudent($assessment, $student);
            return;
        }

        // Show the tool in an iframe
        $breadcrumbs = $this->getBakery()
            ->produceFromAssessment($assessment, $this->getUser())
            ->add([
                $assessment->getTitle(),
                $this->getRouteIfAuthorized(
                    'learningAssessment.getSummary',
                    [
                        'assessmentId' => $assessment->getId(),
                        'assessment'   => $assessment,
                        'studentId'    => $student->getId(),
                        'student'      => $student,
                    ],
                )
            ])
            ->add(['Create Response for ' . $assessment->getTitle(), '']);

        $this->setAllowBackToTheFuture(false);

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lti/ltiCreateResponse.twig',
            [
                'assessment'  => $assessment,
                'student'     => $student,
                'breadcrumbs' => $breadcrumbs,
            ]
        );
    }

    /**
     * Launches this LTI assessment's resource link, within the context of a
     * student.
     *
     * @api   GET /learning/assessments/:assessmentId/lti/launch/student/:studentId
     * @since 21.1.0
     *
     * @param Model $assessment
     * @param User  $student
     */
    public function getLaunchAssessmentAsStudent(Model $assessment, User $student): void
    {
        $message = $this->buildLaunchResourceLinkMessage($assessment, $this->getUser(), $student);

        $this->setAllowBackToTheFuture(false);

        // And send message to template, where it's POSTed
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningAssessment/lti/messageForm.twig',
            ['message' => $message]
        );
    }

    /**
     * Build a resource link launch message, which will then be used to launch
     * the LTI tool's assessment.
     *
     * @param Model $assessment
     * @param User  $currentUser The current user
     * @param User  $actingUser  The user who the current user is acting as:
     *                           if not themselves, it may be a student.
     * @return LtiMessageInterface
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildLaunchResourceLinkMessage(
        Model $assessment,
        User $currentUser,
        User $actingUser
    ): LtiMessageInterface {
        // if assessment's lti tool is not hydrated properly, hydrate
        if ($assessment->getLtiTool() && empty($assessment->getLtiTool()->getDeploymentId())) {
            $ltiTool = $this->ltiToolMapper->findById($assessment->getLtiTool()->getId());
            $assessment->setLtiTool($ltiTool);
        }

        return (new LaunchFactory(
            $this->registrations,
            $this->getAccess(),
            $this->routeGenerator,
            $this->instance,
            $this->getConfigLoader(),
            $this->userCodec
        ))->buildForAssessment($assessment, $currentUser, $actingUser, $this->requestQuery->getInt('height'));
    }
}
