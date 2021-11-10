<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

use Alaress_Date_Format;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Service\Score\ScoreServiceInterface;
use OAT\Library\Lti1p3Ags\Service\Score\Server\Handler\ScoreServiceServerRequestHandler;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\NrpsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use Psr\Cache\CacheItemPoolInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\LineItem\SingleAssessmentRepository;
use Schoolbox\LTI\Provider as LtiTool;
use Schoolbox\LTI\Score\SingleAssessmentScoreRepository;
use Schoolbox\LTI\Util\CustomClaimFactory;
use Schoolbox\LTI\Util\PlatformInstanceClaimFactory;
use Schoolbox\LTI\Util\RoleClaimFactory;
use Schoolbox\LTI\Util\RoleScopeMentorClaimFactory;
use Schoolbox\LTI\Util\ServerRequestFactory;
use Schoolbox\LTI\Util\UserCodec;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Url\Factory;
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
     * Cache for access tokens.
     */
    private CacheItemPoolInterface $accessTokenCache;

    /**
     * Constructor
     *
     * @param UserMapper                      $userMapper
     * @param Error\Mapper                    $errorMapper
     * @param MapperProxy                     $assessmentMapper
     * @param WorkTypeMapper                  $workTypeMapper
     * @param Access                          $access
     * @param Transactor                      $transactor
     * @param Assessment\Breadcrumb\Factory   $bakery
     * @param Term\Mapper                     $termMapper
     * @param Participant\DataSource          $participantDataSource
     * @param CourseSpecificationMapper       $courseSpecificationMapper
     * @param UnitSpecificationMapper         $unitSpecificationMapper
     * @param Replicator                      $replicator
     * @param LtiTool\Mapper                  $ltiToolMapper
     * @param LtiAssessment\Mapper            $ltiAssessmentMapper
     * @param LtiAssessmentScore\Mapper       $ltiAssessmentScoreMapper
     * @param RegistrationRepositoryInterface $registrations
     * @param Instance                        $instance
     * @param UserCodec                       $userCodec
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
        Replicator $replicator,
        LtiTool\Mapper $ltiToolMapper,
        LtiAssessment\Mapper $ltiAssessmentMapper,
        LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper,
        RegistrationRepositoryInterface $registrations,
        Instance $instance,
        UserCodec $userCodec,
        CacheItemPoolInterface $accessTokenCache
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
            $replicator
        );
        $this->ltiToolMapper            = $ltiToolMapper;
        $this->ltiAssessmentMapper      = $ltiAssessmentMapper;
        $this->ltiAssessmentScoreMapper = $ltiAssessmentScoreMapper;
        $this->registrations            = $registrations;
        $this->instance                 = $instance;
        $this->userCodec                = $userCodec;
        $this->accessTokenCache         = $accessTokenCache;
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
            'acceptingResponses' => true,
            'ltiTools'           => $this->ltiToolMapper->findByColumn(
                'lti_version',
                LtiTool\Model::LTI_VERSION_1_3,
            )->filter(function (LtiTool\Model $model) {
                // Only LTI tools with a deep linking or launch URL are supported,
                // otherwise there's no way to select content for them.
                return $model->getDeepLinkingUrl() || $model->getLaunchUrl();
            })->sortNaturally('name'),
        ];
    }

    /**
     * Placeholder for progress summary for all students on an LTI assessment.
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @since 21.1.0
     *
     * @param IAssessable|Model $assessment
     */
    public function getStatus(IAssessable $assessment)
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/status.twig',
                [
                    'type'        => Assessment\AssessmentType::objectToString($assessment),
                    'assessment'  => $assessment,
                    'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                                                       ->add([ $assessment->getTitle(), '' ]),
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
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
            $this->response->redirect->to($sebDownload);
            return;
        }

        // Display a timeline for the student's participation
        $participant = $assessment->getParticipantIfExtant($student);
        $timeline    = null;
        if ($participant) {
            $timeline = (new Assessment\Timeline\Generic($participant))->getForUser($this->getUser());
        }

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/summary.twig',
                [
                    'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                                                  ->add([ $assessment->getTitle(), '' ]),
                    'assessment'  => $assessment,
                    'participant' => $participant,
                    'student'     => $student,
                    'timeline'    => $timeline,
                    'returnUrl'   => $this->request->query->get(Assessment\Redirect::RETURN_URL),
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
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
        $scoreModel = $this->ltiAssessmentScoreMapper->createModel()
            ->setStudent($student)
            ->setIsResponse($this->request->post->getBool('isResponse', false))
            ->setIsFeedback($this->request->post->getBool('isFeedback', false))
            ->setScoreGiven(
                filter_var($this->request->post->get('scoreGiven'), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            )
            ->setScoreMaximum(
                filter_var($this->request->post->get('scoreMaximum'), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            )
            ->setComment($this->request->post->get('comment'))
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
        $markingUserId = $this->request->post->getInt('markingUser');
        $scoreModel->setMarkingUser(
            $markingUserId
                ? (new User())->setId($markingUserId)
                : null
        );

        // Save
        $this->ltiAssessmentScoreMapper->save($scoreModel);

        // Then redir to the return URL, or the assessment's folder
        $encodedReturnUrl = $this->request->query->get(Redirect::RETURN_URL);
        $this->response->redirect->afterPost(
            isset($encodedReturnUrl)
                ? Redirect::decode($encodedReturnUrl)
                : ('/homepage' . $assessment->getBaseContext()->getFolder()->getId()),
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

        // And send message to template, where it's POSTed
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@learningAssessment/lti/messageForm.twig',
                ['message' => $message]
            )
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

        // And send message to template, where it's POSTed
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@learningAssessment/lti/messageForm.twig',
                ['message' => $message]
            )
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
     */
    private function buildLaunchResourceLinkMessage(
        Model $assessment,
        User $currentUser,
        User $actingUser
    ): LtiMessageInterface {
        $assessmentContext = $assessment->getBaseContext()->getFolder();

        // Create an LTI resource link launch, send it to the iframe
        $registration = $this->registrations->find($assessment->getLtiTool()->getDeploymentId());

        // Roles: dependent on whether user is a participant or instructor
        // TODO determine how roles should be set if $actingUser != $currentUser
        $roleClaimFactory = new RoleClaimFactory($this->getAccess());
        $roles            = array_merge(
            $roleClaimFactory->buildInstitutionRoles($currentUser),
            $roleClaimFactory->buildContextRolesForAssessment($currentUser, $assessment),
        );

        // Additional claims:
        $additionalClaims = [];

        // Resource link claim: talk about the assessment
        $resourceLinkClaim = new ResourceLinkClaim(
            (string)$assessment->getId(),
            $assessment->getLtiAssessmentTitle(),
            $assessment->getLtiAssessmentText(),
        );
        $additionalClaims[] = $resourceLinkClaim;

        // Context claim: talk about the folder
        // I have seen no explanation for what context type actually is,
        // thus I will make something up.
        $contextClaim = new ContextClaim(
            (string)$assessmentContext->getId(),
            [ContextClaim::TYPE_COURSE_SECTION],
            $assessmentContext->getCodeString(),
            $assessmentContext->getTitle(),
        );
        $additionalClaims[] = $contextClaim;

        // PlatformInstanceClaim: talk about the Schoolbox instance
        $platformInstanceClaim = (new PlatformInstanceClaimFactory($this->instance))->build();
        $additionalClaims[]    = $platformInstanceClaim;

        // Role-scope mentor claims:
        // for parents, this might be their kids
        // for kids, this won't be anyone
        // for teachers, this will be all participants in the assessment
        $roleScopeMentorClaim = (new RoleScopeMentorClaimFactory($this->getAccess()))
            ->buildForAssessment($currentUser, $assessment);
        if ($roleScopeMentorClaim) {
            $additionalClaims[] = $roleScopeMentorClaim;
        }

        // Launch presentation:
        // should come from assessment, but doesn't yet

        // LIS claim: I don't think we support this yet

        // Custom claims, with whatever was passed from the product
        $customClaim = (new CustomClaimFactory())
            ->withUser($actingUser)
            ->withActualUser($currentUser)
            ->withResourceLink($resourceLinkClaim)
            ->withContext($contextClaim)
            ->withPlatformInstance($platformInstanceClaim)
            ->buildForAssessment($assessment);
        if ($customClaim) {
            $additionalClaims[] = $customClaim;
        }

        // AGS claims:
        // if a line item is provided, send that
        // (Currently, score is the only service Schoolbox provides)
        $baseUrl = (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https');
        $additionalClaims[] = new AgsClaim(
            [ScoreServiceInterface::AUTHORIZATION_SCOPE_SCORE],
            null,
            (clone $baseUrl)
                ->setPath(
                    $this->router->generate(
                        'learningAssessment.getLineItem',
                        ['assessmentId' => $assessment->getId()]
                    )
                )
                ->toString(),
        );

        // NPRS claim:
        // provide the folder as the context, with a resource ID parameter
        $additionalClaims[] = new NrpsClaim(
            (clone $baseUrl)
                ->setPath(
                    $this->router->generate(
                        'folder.getMemberships',
                        ['id' => $assessment->getBaseContext()->getFolder()->getId()]
                    )
                )
                ->setQuery(['rlid' => $assessment->getId(),])
                ->toString(),
        );

        // Resource link request
        $builder = new PlatformOriginatingLaunchBuilder();
        return $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            $assessment->getLtiAssessmentUrl() ?? $assessment->getLtiTool()->getLaunchUrl(),
            $this->userCodec->encode($currentUser),
            null,
            array_unique($roles),
            $additionalClaims,
        );
    }

    /**
     * LTI line item service endpoint.
     *
     * Currently, this does nothing: however it must exist so that the resource
     * link launch request can generate a score publish service endpoint.
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#service-endpoint:
     * > The scores service endpoint MUST be the line item resource URL with the
     * > path appended with '/scores'.
     *
     * @param Model $assessment
     *@since 21.1.0
     *
     * @api   GET /learning/assessments/:assessmentId/lti
     */
    public function getLineItem(Model $assessment): void
    {
        throw new NotFound();
    }

    /**
     * Score publish service endpoint.
     *
     * @api   POST /learning/assessments/:assessmentId/lti/scores
     * @since 21.1.0
     *
     * @param Model $assessment
     */
    public function postScorePublish(Model $assessment): void
    {
        // Build and validate score publish request
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-ags/doc/score/platform/
        $request = (new ServerRequestFactory())->build($this->request);

        // Line item repository: consists only of a single assessment
        $lineItemRepository = new SingleAssessmentRepository($assessment);

        // Score repository: consists only of a single assessment, and its scores
        $scoreRepository = new SingleAssessmentScoreRepository(
            $assessment,
            $this->ltiAssessmentMapper,
            $this->ltiAssessmentScoreMapper,
            $this->userCodec,
        );

        // Access token sanity checker: validates the request token was created by a tool
        $validator = new RequestAccessTokenValidator($this->registrations);

        // Access token validity checker
        // TODO move this into \Schoolbox\Application\WebRequest\Kernel::extractUserFromAuthHeader
        $resourceServerValidator = new ResourceServer(
            new AccessTokenRepository($this->accessTokenCache),
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
        );
        $request = $resourceServerValidator->validateAuthenticatedRequest($request);

        // Handle the request: this validates the request body
        $handler = new ScoreServiceServerRequestHandler($lineItemRepository, $scoreRepository);

        // Serve the request
        $server = new LtiServiceServer($validator, $handler);
        $this->populateResponseFromPsr7Response($server->handle($request));
    }
}
