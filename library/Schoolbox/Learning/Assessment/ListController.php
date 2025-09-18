<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lumberjack\Logger;
use Alaress\Module\Registry;
use Alaress\Web;
use Alaress\Web\Controller\Exception;
use Alaress\Web\Request\Values;
use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use DomainException;
use InvalidArgumentException;
use LearnositySdk\Exceptions\ValidationException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Feedback\Visibility as FeedbackVisibility;
use Schoolbox\Learning\Assessment\Participant\DataSource;
use Schoolbox\Learning\Assessment\Participant\ParticipationHelper;
use Schoolbox\Learning\Assessment\Project\ResultCache\ValidCache;
use Schoolbox\Learning\Assessment\Seb\Payload;
use Schoolbox\Learning\Assessment\Seb\Payload as SebPayload;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\Lti\Response\Model as LtiResponse;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Mapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Assessment\Participant\Plan\Model as PlanModel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as Folder;
use Schoolbox\Role;
use Schoolbox\Url\Factory as UrlFactory;
use Schoolbox\Url\Uri;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Traversable;
use UnexpectedValueException;
use Alaress_Date_Format;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;

/**
 * Class ListController
 *
 * @package Schoolbox\Learning\Assessment
 */
class ListController extends Web\Controller\Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var MapperProxy
     */
    private $assessmentMapper;

    /**
     * @var Access
     */
    private $access;

    /**
     * @var BreadcrumbFactory
     */
    protected BreadcrumbFactory $bakery;


    /**
     * @param UserMapper $userMapper
     * @param Error\Mapper $errorMapper
     * @param MapperProxy $assessmentMapper
     * @param Access $access
     * @param Transactor $transactor
     * @param DataSource $participantDataSource
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @param RosettaStone $rosettaStone
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param null|UrlFactory $urlFactory
     * @param null|string $learnosityVersion
     * @param null|string $learnosityConsumerKey
     * @param null|string $learnosityConsumerSecret
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        Access $access,
        private Transactor $transactor,
        protected Participant\DataSource $participantDataSource,
        protected UnitSpecificationMapper $unitSpecificationMapper,
        private RosettaStone $rosettaStone,
        protected \Schoolbox\Learning\Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        private ?UrlFactory $urlFactory = null,
        private ?string $learnosityVersion = null,
        #[\SensitiveParameter] private ?string $learnosityConsumerKey = null,
        #[\SensitiveParameter] private ?string $learnosityConsumerSecret = null,
    ) {
        $this->setUserMapper($userMapper);
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapper($assessmentMapper);
        $this->setAccess($access);
    }


    /**
     * Offer a SEB Safe Exam Browser download screen
     *
     * @api GET /learning/assessments/{assessmentId}/seb
     * @since 21.0.7
     */
    public function getSebDownload(IAssessable $assessment): void
    {
        $this->response = $this->responseFromTemplate('@learningAssessment/sebDownload.twig', [
            'assessmentId' => $assessment->getId(),
            'assessment' => $assessment,
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([
                $assessment->getTitle(),
                '',
            ]),
        ]);
    }


    /**
     * Offer a SEB Safe Exam Browser config download for the given assessment
     *
     * @api GET /learning/assessments/{assessmentId}/sebConfig
     * @since 21.0.7
     */
    public function getSebConfig(IAssessable $assessment): void
    {
        $sebPayload = new SebPayload(
            $this->getConfigLoader(),
            $this->request->getServerParams()['HTTP_HOST'] ?? '',
            $this->routeGenerator
        );

        $contents = $sebPayload->generate($assessment, $this->getUser());

        /* Send as a downloadable file
         */
        $this->response = $this->responseFromContent($contents)
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Cache-Control', 'private, max-age=1, no-transform')
            ->withHeader('Content-Type', 'application/seb')
            ->withHeader('Content-Length', strlen($contents))
            ->withHeader('Content-Disposition', "attachment; filename=\"{$assessment->getId()}.seb\"");
    }


    /**
     * SEB Safe Exam Browser check
     *
     * Check whether $assessment is protected by seb and if so, decide if you are
     * allowed to get in. If seb wasn't required, then it lets you through anyway.
     *
     * Seb is only enforced upon student roletype
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws RouteNotFound
     * @throws \CFPropertyList\IOException
     * @throws \CFPropertyList\PListException
     * @throws \DOMException
     */
    protected function isSebCheckPasses(IAssessable $assessment): bool
    {
        /* Check whether SEB was required
         */
        if ($this->requestQuery->get(Payload::SEB_KEY)) {
            $this->session->set(Payload::SEB_KEY, $this->requestQuery->get(Payload::SEB_KEY));
        }
        return (new Seb\ValidSebCheck())->isSatisfiedBy(
            $this->request->getServerParams(),
            /* Don't worry about the Browser Exam Key for now
             * We need to generate some platform-specific crypto for it
             */
            'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
            /* Generate a config key hash from the xml that guards this assessment url
             */
            (new SebPayload(
                $this->getConfigLoader(),
                $this->request->getServerParams()['HTTP_HOST'] ?? '',
                $this->routeGenerator
            ))->generateConfigKey($assessment, $this->getUser()),
            $this->session->get(Payload::SEB_KEY)
        );
    }


    /**
     * Retrieve a list of assessments that exist, by context
     *
     * NOTE: this is a debug route that is only available to supers, so don't go referencing this route in twigs...
     * It shows the functionality of the common "assessment component" logic for any context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments?user&readOnly
     * @since 165
     *
     * @param AssessmentContext $context
     * @throws MapperException
     */
    public function getAssessmentsByContext(AssessmentContext $context)
    {
        // it's possible for Aura to bypass this typehint, so let's check again... *sigh*
        if (!$context instanceof AssessmentContext) {
            throw new NotFound();
        }

        $q = $this->requestQuery;

        if ($readOnly = $q->get('readOnly')) {
            // inject dummy to purposefully fail auth checks
            $user = $this->userMapper->createModel();
            $user->getRole()->setType(Role::STUDENT);
        } elseif ($uid = $q->get('user')) {
            // inject the given user
            $user = UserMapper::getInstance()->findById($uid);
        } else {
            // default to session user
            $user = $this->getUser();
        }

        $assessments = $this->getAssessmentMapper()->findAllByContext($context, ['hydrateParticipants' => true]);

        if ($readOnly) {
            $hasContextWrite = false;
        } else {
            // perms check
            $hasContextWrite = $this->getAccess()->getFolderAccess($this->getUser(), $context->getFolder()->getId(), false);
        }

        $data = [
            'user'            => $user,
            'context'         => $context,
            'hasContextWrite' => $hasContextWrite,
            'assessables'     => $assessments,
            'contextType'     => AssessmentContextType::objectToString($context) . 's',
            'assessmentType'  => $q['type'],
            'component_id'    => null,
            'icon'            => '',
            'readOnly'        => $readOnly,
            'compact'         => false,
            '_is_xhr'         => $this->isXhrRequest(),
        ];

        $this->response = $this->responseDependentOnRequest('@learningAssessment/component.twig', $data);
    }


    /**
     * Create a new assessment in the given context
     *
     * @api   POST /learning/{:contextType}/{:contextId}/assessments
     * @param AssessmentContext $context
     * @throws ContainerExceptionInterface
     * @throws Deleted
     * @throws MapperException
     * @throws NotFoundExceptionInterface
     * @throws RouteNotFound
     * @throws Exception
     * @since 165
     */
    public function postCreateAssessment(AssessmentContext $context)
    {
        try {
            // we need to perform some surgery on our "post" data before we send it through
            $post            = $this->requestPost->getArrayCopy();
            $post['context'] = $context;

            $assessmentInfo = $this->persistAssessment($this->getUser(), $post);

            $type       = AssessmentType::objectToString($assessmentInfo['assessment']);
            $controller = $this->getDelegateController($type);

            $explicitReturnUrl = $this->requestPost->get(Redirect::RETURN_URL);

            /* Update project result cache when an activity is spawned in that project
             */
            if ($context instanceof Project) {
                (new Project\ResultCache\ResultCache($this->projectResultCacheMapper))->refresh($this->getAssessmentMapper(), $assessmentInfo['assessment']);
            }

            $this->redirect(
                $controller->determineRedirect(
                    $assessmentInfo['assessment'],
                    $explicitReturnUrl,
                    $assessmentInfo[Redirect::RETURN_URL],
                    $assessmentInfo['relocated'],
                    true,
                    $post['toParticipation'] ?? false
                )
            );
        } catch (InvalidArgumentException $e) {
            //Check to see if the said Course and/or Units were deleted before throwing the message
            if ($context instanceof \Schoolbox\ISoftDeletable && $context->isDeleted()) {
                $this->sendError('Course and/or units were invalid; they might have been deleted.', [], 400, $e->getCode());
            } else {
                $this->sendError('Could not save activity. ', [$e->getMessage()], 400, $e->getCode());
            }
            return;
        } catch (DomainException $e) {
            $this->sendError('Could not save activity. ', [$e->getMessage()], 400, $e->getCode());
            return;
        }
    }


    /**
     * POST action for reordering assessments.
     *
     * GET vars:
     * * returnUrl
     *
     * POST vars:
     * * id: a list of assessment IDs, in order of what their schedule should be
     *
     * @api   POST /learning/{contextType}/{contextId}/assessments/reorder
     * @since 165
     *
     * @param AssessmentContext $context
     * @throws LogicException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     */
    public function postReorderAssessments(AssessmentContext $context)
    {
        /**
         * @var IAssessable[] $allAssessments
         * @var IAssessable[] $sortedAssessments
         */
        $allAssessments    = $this->getAssessmentMapper()->findAllByContext($context)->indexBy('id');
        $sortedAssessments = new GenericCollection(IAssessable::class);
        foreach ($this->requestPost->get('id', []) as $assessmentId) {
            if (isset($allAssessments[$assessmentId])) {
                // Put it in $sortedAssessments in order
                $sortedAssessments->add($allAssessments[$assessmentId]);
                unset($allAssessments[$assessmentId]);
            }
        }

        // Anything not explicitly ordered gets pushed to the back of the queue
        foreach ($allAssessments as $assessment) {
            $sortedAssessments->add($assessment);
        }

        // Save ordering
        $this->getAssessmentMapper()->sequenceAssessments($sortedAssessments);

        // Redirect to a return URL or something
        // If not provided, redirect to somewhere relevant to context
        $encodedReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            if ($context instanceof Folder) {
                // Folder homepage
                $returnUrl = '/homepage/' . $context->getId();
            } elseif ($context instanceof Project) {
                // Project status page
                $returnUrl = $this->routeGenerator->generate(
                    'learningAssessment.getStatus',
                    ['assessmentId' => $context->getId()]
                );
            } elseif ($context instanceof UnitSpecification) {
                // Course modify page for this unit
                $returnUrl = $this->routeGenerator->generate(
                    'course.modifyCourse',
                    ['id' => $context->getCourse()->getId()]
                );
                $returnUrl .= '#' . $context->getId();
            } elseif ($context instanceof UnitImplementation) {
                // Folder that this course is on
                $returnUrl = '/homepage/' . $context->getCourse()->getFolder()->getId();
            } else {
                // Back to base?
                $returnUrl = '/';
            }
        }
        $this->redirect($returnUrl);
    }

    /**
     * Fetch the controller for the given type of assessment.
     *
     * @param string $type One of the `AssessmentType::TYPE_*` constants
     * @return Controller  The controller for the given assessment type
     * @throws InvalidArgumentException    if the module can't find a controller
     *                                     of the correct type
     * @throws ContainerExceptionInterface something went wrong when building
     *                                     the controller
     * @throws NotFoundExceptionInterface  something went wrong when building
     *                                     the controller
     */
    private function getDelegateController($type): Controller
    {
        $module = Registry::buildModuleFromRegistration($this->di, 'learningAssessment');
        if ($module instanceof Module) {
            // Dependencies need to be set up too
            $module->initControllerDependencies();
            $controller = $module->installControllerDependencies(
                $module->buildControllerForType($type),
                $this->di,
            );
            $controller->setUser($this->getUser());
            return $controller;
        }
        throw new InvalidArgumentException(
            sprintf(
                'Module "learningAssessment" does not exist or is not a "%s" instance',
                Module::class,
            ),
        );
    }

    /**
     * Updates assessment's weight
     *
     * @api   POST /learning/weight/:assessmentId
     *
     * @param IAssessable $assessment
     * @throws MapperException
     */
    public function updateWeight(IAssessable $assessment): void
    {
        $weight = (float) $this->requestPost['weight'];
        if ($weight < 0) {
            $this->response = $this->responseAsJson(['error' => 'Invalid weight value'])->withStatus(500);
            return;
        }
        $assessment->setWeight($weight);
        $this->getAssessmentMapper()->save($assessment, $this->getUser());

        /* Update project result cache when an activity gets its weight changed
         * via the markbook ui
         */
        if ($assessment->getContext() instanceof Project) {
            (new Project\ResultCache\ResultCache($this->projectResultCacheMapper))->refresh($this->getAssessmentMapper(), $assessment);
        }

        $this->response = $this->responseAsJson(['message' => 'Successfully updated weighting']);
    }

    /**
     * Updates assessment's visibility
     *
     * @api   POST /learning/visibility/:assessmentId
     *
     * @param IAssessable $assessment
     * @throws MapperException
     */
    public function updateVisibility(IAssessable $assessment): void
    {
        $visibility            = (int)$this->requestPost['visibility'];
        $availableVisibilities = FeedbackVisibility::getAvailableVisibilities($this->getConfigLoader()->get('submission_visibility_max'));
        if (!in_array($visibility, $availableVisibilities)) {
            $this->response = $this->responseAsJson(['error' => 'Invalid visibility'])->withStatus(500);
            return;
        }

        // get assessment before the visibility change for notification
        $previous = $this->getAssessmentMapper()->getNotifier()->dehydrate($assessment);
        $assessment->setMarkVisibility($visibility);
        $this->getAssessmentMapper()->save($assessment, $this->getUser());
        // triggering notification explicitly, to notify marked, if necessary
        $this->getAssessmentMapper()->getNotifier()->notify($assessment, $this->getUser(), $previous);

        $this->response = $this->responseAsJson(['message' => 'Loaded successfully']);
    }

    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @since 165
     *
     * @param AssessmentContext $context
     * @throws DomainException
     * @throws Mapper\DefinitionException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     */
    public function getCreateAssessmentForm(AssessmentContext $context)
    {
        // check for a valid type declaration
        if ($type = $this->requestQuery->get('type')) {
            try {
                // delegate to the appropriate sub-controller
                $delegate = $this->getDelegateController($type);
                $delegate->getCreateAssessmentForm($context);
                $this->response = $delegate->getResponse();
                return;
            } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
                // if the client enters an invalid type: ignore
                // and allow passthru to the "unselected" section
            }
        }

        // Did the caller request a returnUrl?
        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL);

        if ($returnUrl) {
            $data[Redirect::RETURN_URL] = $returnUrl;
        }

        // show the generic first-half of the form
        $data['types'] = [
            [
                'id'    => 'due-work',
                'value' => AssessmentType::TYPE_DUE_WORK,
                'label' => $this->rosettaStone->translate('dueWork', false, true),
                'icon'  => 'due-work',
                'title' => 'Create an assessable item for students that can accept submissions',
            ],
            [
                'id'    => 'task',
                'value' => AssessmentType::TYPE_TASK,
                'label' => Task::getDefaultTypeName(),
                'icon'  => 'task',
                'title' => 'A simple activity that will track student completion',
            ],
            [
                'id'    => 'quiz',
                'value' => AssessmentType::TYPE_QUIZ,
                'label' => Quiz::getDefaultTypeName(),
                'icon'  => 'quiz',
                'title' => 'Ask a series of questions with automatic marking',
            ],
        ];

        $participants = $this->requestQuery->get('student');

        if ($participants) {
            /* Get participant information
               */
            $data['participants'] = $this->userMapper
                ->findByColumn('id', explode(',', $participants))
                ->map(fn($participant) => [
                    'id'       => $participant->getId(),
                    'fullName' => $participant->getFullName(),
                ]);
            /* Get the source/parent assessment id
             */
            $data['customisedFor'] = $this->requestQuery->get('original');
        }

        /* Only duework, task and quiz can have customised activities
         */
        if (!isset($participants)) {
            if ($this->getConfigLoader()->get('lti_assessments_enable')) {
                $data['types'][] = [
                    'id'    => 'lti',
                    'value' => AssessmentType::TYPE_LTI_ASSESSMENT,
                    'label' => LtiAssessment::getDefaultTypeName(),
                    'icon'  => 'lti',
                    'title' => 'Connect an assessment provided by an LTI tool',
                ];
            }
            /* The tooltip for Lesson Plans came from the slack channel
             * https://alaress.slack.com/archives/C04R588EKPS/p1678336098480059
             */
            $data['types'][] = [
                'id'    => 'lesson-plan',
                'value' => AssessmentType::TYPE_LESSON_PLAN,
                'label' => $this->rosettaStone->translate('lessonPlan', false, true),
                'icon'  => 'lesson-plan',
                'title' => 'A detailed guide that outlines a lesson\'s purpose and activities',
            ];

            // TODO, 'types' should just be the 3 objects, and twig should do the rest
            // TODO (see icon macro for 'id', assessment.typeName for 'label', types.objectToString($assessment) for 'value')

            // Ensure we don't provide an option to spawn a Project inside a Project context
            if (!$context instanceof Project) {
                $item = [
                    'id'    => 'project',
                    'value' => AssessmentType::TYPE_PROJECT,
                    'label' => $this->rosettaStone->translate('project', false, true),
                    'icon'  => 'project',
                    'title' => 'Setup a container for multiple activities that are related',
                ];
                array_unshift($data['types'], $item);
            }
        }

        $data['contextType'] = AssessmentContextType::objectToString($context) . 's';
        $data['baseContextType'] = AssessmentContextType::objectToString($context->getBaseContext()) . 's';
        $data['context']     = $context;
        $data['breadcrumbs'] = [
            ['Learning', $this->routeGenerator->generate('learning.getClasses')],
            [$context->getFolder()->getTitle(), "/homepage/{$context->getFolder()->getId()}"],
        ];
        if ($context instanceof Project) {
            $data['breadcrumbs'][] = [
                $context->getTitle(),
                $this->routeGenerator->generate(
                    'learningAssessment.getStatus',
                    [
                        'assessment'   => $context,
                        'assessmentId' => $context->getId(),
                    ]
                ),
            ];
        }

        $this->response = $this->responseFromTemplate('@learningAssessment/create.twig', $data);
    }


    /**
     * Generic method for create/update of an assessment.
     *
     * persistAssessment() CANNOT make any assumptions about redirects
     * Redirects should be dealt with by actual controller methods, since
     * the returnUrl is manipulated by javascript in particular contexts
     *
     * @param User              $user
     * @param array|Traversable $postData
     * @param IAssessable|null  $assessment
     * @return array
     * @throws MapperException
     * @throws Deleted
     * @throws DomainException
     */
    protected function persistAssessment(User $user, $postData, ?IAssessable $assessment = null): array
    {
        /* If an assessment couldn't be produced, bail out
         * and let mage see the errors set by $this->sendError
         *
         * Controller must return early for that to happen
         */
        $db = $this->getUserMapper()->getDatabase();

        try {
            $db->beginTransaction();

            if (isset($postData['customisedFor'])) {
                $sourceAssessment = $this->getAssessmentMapper()->findById($postData['customisedFor']);

                if (0 === strcasecmp($sourceAssessment->getTitle(), $postData['title'])) {
                    throw new \InvalidArgumentException('The activity can not have the same title as the original activity');
                }

                /* Excluding the participant from the original assessment when assigned to another assessment
                 */
                if (isset($postData['original-participant-exclude'])) {
                    $planMapper = $this->participantDataSource->getPlanMapper();
                    $plans = $planMapper->findByColumn('id', $postData['original-participant-plan']);
                    $this->saveParticipationData($sourceAssessment, $plans, $postData['original-participant-exclude'], $postData['original-participant-plan']);
                }
            }


            // Create a notifier proxy so that I may notify about assessment changes
            $notifier = $this->assessmentMapper->getNotifier();
            $before   = $notifier->dehydrate($assessment);

            /* Figure out what kind of assessment was chosen
             * and spawn a new instance of it to manipulate
             */

            // Clear assessment if the context is changed
            $relocatedContext   = $assessment ? $this->getRelocatedContext($assessment, $postData['locationId'] ?? '') : '';
            $originalAssessment = null;

            if ($relocatedContext) {
                $postData['context'] = $relocatedContext;
                $originalAssessment  = $assessment;
                $assessment          = null;
            }

            /* Decide whether we have to invalidate project_result cache
             * Have to compare $postData and $assessment -before- it's transacted/updated
             */
            $projectResultCacheDetector = new ValidCache();

            if ($assessment instanceof Project) {
                $cacheValid = $projectResultCacheDetector->isSatisfiedByProject($postData, $assessment);
            } else {
                $cacheValid = $projectResultCacheDetector->isSatisfiedByActivity($postData, $assessment);
            }

            $assessmentFactory = new Factory($user, $postData, $postData['assessmentType'], $originalAssessment, $this->errorMapper);
            $assessment        = $assessmentFactory->produce($assessment);

            $transactionSummary = $this->transactor->transact($assessment, $this->getUser(), $before, $originalAssessment);

            // assessment is saved; persist plans

            // Note: in the returned collection
            //    - any plans CREATED by this method will be NEGATIVELY INDEXED (the object has a real ID though)
            //    - any plans UPDATED by this method will be ID INDEXED
            $plans = $this->savePlanData($assessment, $postData['plans'] ?? []);

            $this->saveParticipationData($assessment, $plans, $postData['participant-include'] ?? [], $postData['participant-plan'] ?? []);
        } catch (\InvalidArgumentException $e) {
            $db->rollBack();
            /* InvalidArgumentException is thrown when a valid context is missing
             * such as it's been deleted, never existed, or bad data
             */
            throw $e;
        } catch (\Exception $e) {
            $db->rollback();
            // cast
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }

        // If we're here the assessment must have saved properly.
        // Commit and send the notifications
        $db->commit();
        // Retrieve assessment, as I need the assessment's participation
        $assessment = $this->assessmentMapper->findByIdForReview($assessment->getId());

        // And send notifications
        $notifier->notify($assessment, $this->getUser(), $before);

        /* Reprocess project marks if its cache is invalid
         * Similar to the cli tool's algorithm
         */
        if (!$cacheValid) {
            (new Project\ResultCache\ResultCache($this->projectResultCacheMapper))->refresh($this->assessmentMapper, $assessment);
        }

        return $transactionSummary;
    }

    /**
     * @param IAssessable                  $assessment
     * @param GenericCollection<PlanModel> $plans
     * @param array                        $inclusionFlags  userId => 0 ... excluded, 1 ... included
     * @param array                        $userPlanIndices userId => planId
     * @throws MapperException
     */
    private function saveParticipationData(IAssessable $assessment, GenericCollection $plans, array $inclusionFlags, array $userPlanIndices): void
    {
        $assessmentDataModel = $this->assessmentMapper->translateToDataModel($assessment);
        $participationHelper = (new Participant\ParticipationHelper($assessmentDataModel, $this->participantDataSource, $this->getUserMapper(), $this->assessmentMapper));
        // plans are saved; persistipation
        foreach ($inclusionFlags as $userId => $userIncluded) {
            /** @var PlanModel|null $userPlan */
            $userPlan = $plans[$userPlanIndices[$userId]];
            if ($userIncluded) {
                if (!$userPlan) {
                    throw new DomainException("User {$userId} was assigned index {$userPlanIndices[$userId]}, but there's no plan with that index (valid plan indices were " . json_encode($plans->keys()) . ')');
                }
                $participationHelper->include($userId, $userPlan);
            } else {
                $participationHelper->exclude($userId, $userPlan);
            }
        }
    }

    /**
     * The return of this method will be a UNION of all plans that exist for the given assessment ID
     * and the new plans specified by $postPlans. New plans will be persisted to the database (and thus have a real
     * ID), but in the returned collection, they will be NEGATIVELY INDEXED, as given in $postPlans.
     *
     * @param IAssessable $assessment
     * @param array       $postPlans
     * @return GenericCollection|PlanModel[]
     * @throws MapperException
     */
    private function savePlanData(IAssessable $assessment, array $postPlans = [])
    {
        $assessmentId = $assessment->getId();
        $dateFields = ['publish_date',  'open_date', 'due_date', 'close_date', 'created_at', 'updated_at'];

        $planMap = $this->participantDataSource->getPlanMapper();

        // preload the plans list
        $plans = $planMap->findByColumnList(['assessment_id' => $assessmentId])
            ->indexBy('id');

        // obtain this reference for later
        $dynaPlan = $plans->filterBy('isDynamic', PlanMapper::PLAN_TYPE_DYNAMIC)->first();
        // within $postPlans, any positive index is the ID of an existing plan; any negative index is a new plan
        foreach ($postPlans as $planId => $planData) {
            // if planId == 0, it's either the dyna-plan or invalid data, but ignorable either way

            //Handle assessments that do no accept submissions:
            if (($assessment instanceof DueWork && $assessment->getAcceptOnline() == 0) || $assessment instanceof Task) {
                $planData['open_date'] = $planData['publish_date'];
                $planData['close_date'] = $planData['due_date'];
            }

            if ($planId > 0 && isset($plans[$planId])) {
                // existing plan updates
                $plans[$planId]
                    ->setName($planData['name'])
                    ->setDescription($planData['description'])
                    ->setPublishDate(Alaress_Date_Format::newLocal($planData['publish_date']))
                    ->setOpenDate(Alaress_Date_Format::newLocal($planData['open_date']))
                    ->setDueDate(Alaress_Date_Format::newLocal($planData['due_date']))
                    ->setCloseDate(Alaress_Date_Format::newLocal($planData['close_date']));
            } elseif ($planId < 0) {
                // new plan
                unset($planData['id']);

                $planData['assessment_id'] = $assessmentId;
                $planData['is_dynamic']    = PlanMapper::PLAN_TYPE_STATIC;
                $planData['created_at']    = null;

                foreach ($dateFields as $dateField) {
                    if (array_key_exists($dateField, $planData)) {
                        // if the field is falsy, make it null
                        if (!$planData[$dateField]) {
                            $planData[$dateField] = null;
                        }

                        $planData[$dateField] = Alaress_Date_Format::newLocal($planData[$dateField])->getDatabase();
                    }
                }

                // note that we are using the negative index here
                $plans[$planId] = $planMap->hydrate($planData);
            }
        }

        // save all the plans
        $planMap->save($plans);

        // drop the dyna-plan into index 0 as well as its ID to get around a limitation in the POST payload
        if ($dynaPlan) {
            $plans[0] = $dynaPlan;
        }

        return $plans;
    }

    /**
     * Gets new context, if the assessment's context has been changed
     * @param IAssessable $assessment
     * @param string|null $locationId
     * @return AssessmentContext|null
     */
    public function getRelocatedContext(IAssessable $assessment, ?string $locationId): ?AssessmentContext
    {
        if (
            $locationId
            && $assessment->isSpecification()
            && ($assessment->getContext()->getLocationId() !== $locationId)
        ) {
            preg_match('/(unit|project)-([0-9]+)/', $locationId, $match);
            try {
                if (isset($match[1]) && $match[1] === 'unit') {
                    return $this->unitSpecificationMapper->findById($match[2]);
                } elseif (isset($match[1]) && $match[1] === 'project') {
                    $project = $this->assessmentMapper->findById($match[2]);
                    if ($project instanceof Project) {
                        return $project;
                    }
                }
            } catch (MapperException $e) {
                // noop
            }
        }
        return null;
    }

    /**
     * If XHR, return the redirect within JSON, otherwise just tell the browser to do the redirect
     *
     * @param string $url
     */
    protected function redirect($url)
    {
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson([Redirect::RETURN_URL => $url]);
        } else {
            $this->response = $this->redirectAfterPost($url);
        }
    }


    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->userMapper;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @return Error\Mapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @param Error\Mapper $errorMapper
     * @return static
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }


    /**
     * @return MapperProxy
     */
    protected function getAssessmentMapper()
    {
        return $this->assessmentMapper;
    }


    /**
     * @param MapperProxy $assessmentMapper
     * @return static
     */
    protected function setAssessmentMapper($assessmentMapper)
    {
        $this->assessmentMapper = $assessmentMapper;
        return $this;
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     * @return static
     */
    protected function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }


    /**
     * @return BreadcrumbFactory
     */
    protected function getBakery(): BreadcrumbFactory
    {
        return $this->bakery;
    }

    /**
     * Easy access to include students into the default assessment Plan
     *
     * @api   POST /learning/assessments/:assessmentId/:userId/include
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function postInclude(IAssessable $assessment, User $student): void
    {
        $assessmentDataModel = $this->assessmentMapper->translateToDataModel($assessment);
        $partitionHelper = new ParticipationHelper($assessmentDataModel, $this->participantDataSource, $this->userMapper, $this->assessmentMapper);
        try {
            $partitionHelper->include($student->getId(), null);
            // Update project mark as the excluded assessment may affect the project result
            (new \Schoolbox\Learning\Assessment\Project\ResultCache\ResultCache($this->projectResultCacheMapper))
                ->refresh($this->getAssessmentMapper(), $assessment, $student);
            $this->response = $this->responseEmpty(201);
        } catch (MapperException $e) {
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($e->getCode());
            $this->response = $this->responseAsJson($problem->asArray());
        }
    }

    /**
     * Easy access to exclude students from the assessment
     *
     * @api   POST /learning/assessments/:assessmentId/:userId/exclude
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function postExclude(IAssessable $assessment, User $student): void
    {
        $assessmentDataModel = $this->assessmentMapper->translateToDataModel($assessment);
        $partitionHelper = new ParticipationHelper($assessmentDataModel, $this->participantDataSource, $this->userMapper, $this->assessmentMapper);
        try {
            $partitionHelper->exclude($student->getId(), null);

            // Update project mark as the excluded assessment may affect the project result
            (new \Schoolbox\Learning\Assessment\Project\ResultCache\ResultCache($this->projectResultCacheMapper))
                ->refresh($this->getAssessmentMapper(), $assessment, $student);

            $this->response = $this->responseEmpty(201);
        } catch (MapperException $e) {
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($e->getCode());
            $this->response = $this->responseAsJson($problem->asArray());
        }
    }

    /**
     * Displays the iframe variant for the return page for after the instructor
     * has finished with the LTI assessment's status page within the LTI tool.
     *
     * This is intended to be used as the return URL for the resource link
     * launch, if the LTI assessment launches within an iframe: this simply
     * redirects to the window variant in the parent window.
     *
     * @api   GET /learning/assessments/:assessmentId/lti/launch/instructor/return/iframe
     * @since 23.0.0
     *
     * @param int $assessmentId The assessment ID to redirect to.
     *                          Intentionally not preloaded: this is only a
     *                          redirect.
     * @return void
     */
    public function getLaunchAssessmentAsInstructorReturnIframe(int $assessmentId): void
    {
        // Do not check for permissions: this is only a redirect
        $statusUrl = $this->routeGenerator->generate(
            'learningAssessment.getStatus',
            ['assessmentId' => $assessmentId,],
        );
        $returnUrl = new Uri(
            $this->routeGenerator->generate(
                'learningAssessment.getLaunchAssessmentAsInstructorReturnWindow',
                ['assessmentId' => $assessmentId,],
            ),
        );
        $returnUrl = $returnUrl->appendQueryParameters(
            array_filter(
                [
                    'lti_msg'            => $this->requestQuery->get('lti_msg', ''),
                    'lti_errormsg'       => $this->requestQuery->get('lti_errormsg', ''),
                    Redirect::RETURN_URL => $this->requestQuery->get(Redirect::RETURN_URL, $statusUrl),
                ]
            )
        );

        $this->setAllowBackToTheFuture(false);

        // Launches:
        // should automatically redirect, but contain a button if they do not
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningAssessment/lti/return/instructor/iframe.twig',
            ['returnUrl'   => $returnUrl->__toString(),],
        );
    }

    /**
     * Displays the return page for after the instructor has finished with the
     * LTI assessment's status page within the LTI tool.
     *
     * This is intended to be used as the return URL for the resource link
     * launch.
     *
     * @api   GET /learning/assessments/:assessmentId/lti/launch/instructor/return/window
     * @since 23.0.0
     *
     * @param AbstractAssessment $assessment
     * @return void
     */
    public function getLaunchAssessmentAsInstructorReturnWindow(AbstractAssessment $assessment): void
    {
        $statusUrl = $this->getRouteIfAuthorized(
            'learningAssessment.getStatus',
            [
                'assessmentId' => $assessment->getId(),
                'assessment'   => $assessment,
            ],
        );
        $returnUrl = new Uri(
            $this->requestQuery->get(Redirect::RETURN_URL, $statusUrl),
        );
        $returnUrl = $returnUrl->appendQueryParameters(
            array_filter(
                [
                    'lti_msg'      => $this->requestQuery->get('lti_msg', ''),
                    'lti_errormsg' => $this->requestQuery->get('lti_errormsg', ''),
                ]
            )
        );

        $params    = [
            'assessment'  => $assessment,
            'returnUrl'   => $returnUrl->__toString(),
            'breadcrumbs' => $this->getBakery()
                ->produceFromAssessment($assessment, $this->getUser())
                ->add([$assessment->getTitle(), $statusUrl])
        ];

        // Launches:
        // should automatically redirect, but contain a button if they do not
        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lti/return/instructor/window.twig',
            $params,
        );
    }

    /**
     * POST alias for `getLaunchAssessmentAsInstructorReturnIframe`.
     *
     * @api   POST /learning/assessments/:assessmentId/lti/launch/instructor/return/iframe
     * @since 23.0.0
     *
     * @param int $assessmentId
     * @return void
     */
    public function postLaunchAssessmentAsInstructorReturnIframe(int $assessmentId): void
    {
        $this->getLaunchAssessmentAsInstructorReturnIframe($assessmentId);
    }

    /**
     * POST alias for `getLaunchAssessmentAsInstructorReturnWindow`.
     *
     * @api   POST /learning/assessments/:assessmentId/lti/launch/instructor/return/window
     * @since 23.0.0
     *
     * @param AbstractAssessment $assessment
     * @return void
     */
    public function postLaunchAssessmentAsInstructorReturnWindow(AbstractAssessment $assessment): void
    {
        $this->getLaunchAssessmentAsInstructorReturnWindow($assessment);
    }

    /**
     * Displays the return page for after the student has finished with the LTI
     * assessment status page within the LTI tool.
     *
     * This is intended to be used as the return URL for the resource link
     * launch for students.
     *
     * This is intended to be used as the return URL for the resource link
     * launch, if the LTI assessment launches within an iframe: this simply
     * redirects to the window variant in the parent window.
     *
     * @api   GET /learning/assessments/:assessmentId/lti/launch/student/:studentId/return/iframe
     * @since 23.0.0
     *
     * @param int $assessmentId The assessment ID to redirect to.
     *                          Intentionally not preloaded: this is only a
     *                          redirect.
     * @param int $studentId    The student ID to redirect to.
     *                          Intentionally not preloaded: this is only a
     *                          redirect.
     * @return void
     */
    public function getLaunchAssessmentAsStudentReturnIframe(int $assessmentId, int $studentId): void
    {
        // Do not check for permissions: this is only a redirect
        $summaryUrl = $this->routeGenerator->generate(
            'learningAssessment.getSummary',
            [
                'assessmentId' => $assessmentId,
                'studentId'    => $studentId,
            ],
        );

        $returnUrl = new Uri(
            $this->routeGenerator->generate(
                'learningAssessment.getLaunchAssessmentAsStudentReturnWindow',
                [
                    'assessmentId' => $assessmentId,
                    'studentId'    => $studentId,
                ],
            ),
        );

        $returnUrl = $returnUrl->appendQueryParameters(
            array_filter(
                [
                    'lti_msg'            => $this->requestQuery->get('lti_msg', ''),
                    'lti_errormsg'       => $this->requestQuery->get('lti_errormsg', ''),
                    Redirect::RETURN_URL => $this->requestQuery->get(Redirect::RETURN_URL, $summaryUrl),
                ]
            )
        );

        // Launches:
        // should automatically redirect, but contain a button if they do not
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningAssessment/lti/return/student/iframe.twig',
            ['returnUrl' => $returnUrl->__toString(),],
        );
    }

    /**
     * Displays the return page for after the student has finished with the LTI
     * assessment status page within the LTI tool.
     *
     * This is intended to be used as the return URL for the resource link
     * launch for students.
     *
     * @api   GET /learning/assessments/:assessmentId/lti/launch/student/:studentId/return/window
     * @since 23.0.0
     *
     * @param AbstractAssessment $assessment
     * @param User               $student
     * @return void
     */
    public function getLaunchAssessmentAsStudentReturnWindow(AbstractAssessment $assessment, User $student): void
    {
        $summaryUrl = $this->getRouteIfAuthorized(
            'learningAssessment.getSummary',
            [
                'assessmentId' => $assessment->getId(),
                'assessment'   => $assessment,
                'studentId'    => $student->getId(),
                'student'      => $student,
            ],
        );

        $returnUrl  = new Uri($this->requestQuery->get(Redirect::RETURN_URL, $summaryUrl));
        $returnUrl  = $returnUrl->appendQueryParameters(
            array_filter(
                [
                    'lti_msg'      => $this->requestQuery->get('lti_msg', ''),
                    'lti_errormsg' => $this->requestQuery->get('lti_errormsg', ''),
                ]
            )
        );

        $params    = [
            'assessment'  => $assessment,
            'returnUrl'   => $returnUrl->__toString(),
            'breadcrumbs' => $this->getBakery()
                ->produceFromAssessment($assessment, $this->getUser())
                ->add([$assessment->getTitle(), $summaryUrl])
        ];

        // Launches:
        // should automatically redirect, but contain a button if they do not
        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lti/return/student/window.twig',
            $params,
        );
    }

    /**
     * POST alias for `getLaunchAssessmentAsStudentReturnIframe`.
     *
     * @api   POST /learning/assessments/:assessmentId/lti/launch/student/:studentId/return/iframe
     * @since 23.0.0
     *
     * @param int $assessmentId
     * @param int $studentId
     * @return void
     */
    public function postLaunchAssessmentAsStudentReturnIframe(int $assessmentId, int $studentId): void
    {
        $this->getLaunchAssessmentAsStudentReturnIframe($assessmentId, $studentId);
    }

    /**
     * POST alias for `getLaunchAssessmentAsStudentReturnWindow`.
     *
     * @api   POST /learning/assessments/:assessmentId/lti/launch/student/:studentId/return/window
     * @since 23.0.0
     *
     * @param AbstractAssessment $assessment
     * @param User               $student
     * @return void
     */
    public function postLaunchAssessmentAsStudentReturnWindow(AbstractAssessment $assessment, User $student): void
    {
        $this->getLaunchAssessmentAsStudentReturnWindow($assessment, $student);
    }

    /**
     * Creating new learning plan customisation for the assessment
     *
     * @param IAssessable $assessment
     * @return void
     * @throws MapperException
     */
    public function postCreateAssessmentParticipationLearningPlan(IAssessable $assessment): void
    {
        $postData = $this->requestPost;

        $this->persistAssessmentParticipationLearningPlan($assessment, $postData);
    }

    /**
     * Updating existing learning plan customisation
     *
     * @param IAssessable $assessment
     * @return void
     * @throws MapperException
     */
    public function postUpdateAssessmentParticipationLearningPlan(IAssessable $assessment): void
    {
        $postData = $this->requestPost;

        $this->persistAssessmentParticipationLearningPlan($assessment, $postData);
    }


    /**
     * get assessment info for participant (for markbook popout feedback)
     * we need all the info, draft, rubric, etc
     *
     * @param User $student
     * @param IAssessable $assessment
     * @return void
     * @throws \JsonException
     * @throws ValidationException
     * @api GET /learning/assessments/{assessmentId}/{studentId}/info
     */
    public function getInfo(User $student, IAssessable $assessment): void
    {
        // hydrate participant
        $assessments = $this->getAssessmentMapper()->createCollection([$assessment]);
        $this->getAssessmentMapper()->loadParticipantsIntoAssessmentCollection($assessments, $student, true);

        $participant = $assessment->getParticipantIfExtant($student);

        $maxMarks = null;
        // If this is a quiz, we need to use the learnosity API to get the max marks
        if (
            $assessment instanceof Quiz &&
            $this->learnosityConsumerKey &&
            $this->learnosityConsumerSecret &&
            $this->learnosityVersion &&
            $this->urlFactory instanceof UrlFactory
        ) {
            if ($participant && $assessment->getMarkScheme() instanceof TotalMarkFractional) {
                /* Ask learnosity to find out how much their attempt *would* be worth
                 * based on the current set of questions if any of the following occurs:
                 *
                 *     - this participant never made an attempt.
                 *     - this participant's attempt is still in progress (db row won't exist yet).
                 *     - most recent response's marksAvailable is zero.
                 *         this occurs when you spawn an arbitrary attempt with a Letter Grade,
                 *         then changed it to Total Mark; we never asked or recorded what the
                 *         quantitative total actually was. Deeper in the andyland part of the
                 *         swamp is a series of floatval() calls that cast nulls to zero.
                 *         TODO maybe find out the breadth of this idea and undo it to support null
                 */
                /** @var QuizAttempt $mostRecentResponse */
                $mostRecentResponse = $participant->findMostRecentResponse();

                if (!$mostRecentResponse) {
                    $maxMarks = null;
                } elseif (!$mostRecentResponse->isSubmitted()) {
                    $maxMarks = null;
                } else {
                    $maxMarks = $mostRecentResponse->getMarksAvailable();
                }
            }

            /* Only need to fetch from Learnosity if we didn't find it above */
            if ($maxMarks === null) {
                /* Probably inject this one day, but for now,
                     * I want to avoid dumping Yet Another Thing into the kernel
                     * I don't have a convenient means to access SERVER_NAME except like this, sadly
                     */
                $logger = new Logger(STDOUT, STDOUT, \Monolog\Level::Debug, [], true, 'learnosity');

                /* TODO Derive from learnosity atm
                 *   Consider caching locally the standing maximum available marks for the assessment in the absence of an attempt.
                 *   This only matters when it's a Total Mark markscheme: everything else is out of 100
                 */
                $api = new Api(
                    $this->urlFactory->buildFullyQualified()->getHost(),
                    $this->learnosityVersion,
                    $this->learnosityConsumerKey,
                    $this->learnosityConsumerSecret,
                    $logger
                );

                $maxMarks = (new Helper($api, $logger))->findAvailableMarks($assessment->getLearnosityReference());
            }
        }

        /* A list of Responses and Floating Feedbacks, as per the Submission History
        */
        $feedback = [
            'id' => null,
            'comment' => null,
            'published' => null,
            'normalisedMark' => null,
            'humanReadable' => null,
            'rubric' => null,
            'resubmissionRequired' => null,
            'rubricMarkedGrid' => null,
            'responseWithKamiDocs' => null,
            'fileCount' => null,
            'humanReadableInstructorMark' => null,
            'completionStatus' => null,
            'scoreGiven' => null,
            'scoreMaximum' => null,
        ];

        // all things have this, even if there are no submissions...
        $response = [
            'id'  => null,
            'isSubmitted' => null,
            'isDraft' => null,
            'isSubmittedOffline' => null,
            'fileCount' => null,
            'isSubmittedOnTime' => null,
            'learnosityReference' => null,
            'comment' => null,
            'duration' => null,
            'humanReadableMark' => null,
            'normalisedMark' => null,
            'marksAvailable' => null,
            'isSelfAssessed' => null,
            'completionStatus' => null,
            'scoreGiven' => null,
            'scoreMaximum' => null,
        ];

        if ($participant) {
            $latestResponse = $participant->findMostRecentResponse();
            $latestFeedback = $participant->findMostRecentInstructorGivenFeedback();

            if ($latestResponse) {
                $response['id'] = $latestResponse->getId();
                $response['isSubmitted'] = (bool)$latestResponse->getId();
                $response['isSubmittedOnTime'] = $latestResponse->isSubmittedOnTime();

                // now for assessment type specific data
                if ($latestResponse instanceof DueWorkResponse) {
                    $response['isSubmitted'] = $latestResponse->isSubmitted();
                    $response['isDraft'] = $latestResponse->isDraft();
                    $response['isSubmittedOffline'] = $latestResponse->isSubmittedOffline();
                    $response['fileCount'] = $latestResponse->getAllFiles()->count();
                } elseif ($latestResponse instanceof TaskResponse) {
                    $response['isSubmitted'] = $latestResponse->isSubmitted();
                    $response['completionStatus'] = $latestResponse->getCompletionStatus();
                    $response['isSelfAssessed'] = $latestResponse->isSelfAssessed();
                } elseif ($latestResponse instanceof LtiResponse) {
                    $response['scoreGiven'] = $latestResponse->getScoreGiven();
                    $response['scoreMaximum'] = $latestResponse->getScoreMaximum();
                    $response['comment'] = $latestResponse->getComment();
                } elseif ($latestResponse instanceof QuizAttempt) {
                    $response['learnosityReference'] = $latestResponse->getLearnosityReference();
                    $response['marksAvailable'] = $latestResponse->getMarksAvailable();
                    $response['duration'] = $latestResponse->getDuration();
                    $response['comment'] = $latestResponse->getComment();
                    $response['humanReadableMark'] = $latestResponse->getHumanReadableMark();
                    $response['normalisedMark'] = $latestResponse->getNormalisedMark();
                }
            }

            if ($latestFeedback) {
                $feedback['id'] = $latestFeedback->getId();
                $feedback['comment'] = $latestFeedback->getComment();
                $feedback['published'] = $latestFeedback->isPublished();
                $feedback['normalisedMark'] = $latestFeedback->getNormalisedMark();
                $feedback['humanReadable'] = $latestFeedback->getHumanReadableMark();

                // now for assessment type specific data
                if ($latestFeedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model) {
                    /** @var \Schoolbox\Learning\Assessment\DueWork\Feedback\Model $latestFeedback  */
                    /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
                    $feedback['rubric'] = $assessment->getUseRubric();
                    $feedback['resubmissionRequired'] = $latestFeedback->getResubmissionRequired();
                    $feedback['rubricMarkedGrid'] = $latestFeedback->getRubricMarkedGrid();
                    $feedback['responseWithKamiDocs'] = $latestFeedback->hasResponseWithKamiDocs();
                    $feedback['fileCount'] = $latestFeedback->getAllFiles()->count();
                    $feedback['humanReadableInstructorMark'] = $latestFeedback->getHumanReadableInstructorMark();
                } elseif ($latestFeedback instanceof \Schoolbox\Learning\Assessment\Task\Feedback\Model) {
                    $feedback['completionStatus'] = $latestFeedback->getCompletionStatus();
                } elseif ($latestFeedback instanceof \Schoolbox\Learning\Assessment\Lti\Feedback\Model) {
                    $feedback['scoreGiven'] = $latestFeedback->getScoreGiven();
                    $feedback['scoreMaximum'] = $latestFeedback->getScoreMaximum();
                }

                // coalesce humanReadableInstructorMark with humanReadable if humanReadableInstructorMark wasn't set
                $feedback['humanReadableInstructorMark'] = $feedback['humanReadableInstructorMark'] ?? $feedback['humanReadable'];
            }
        }

        /** Build all the mark information so the markbook can figure out the latest state */
        $authoritativeFeedback = $participant?->getAuthoritativeFeedback(true);
        $authoritativeFeedbackDraft = $participant?->getAuthoritativeFeedback(false);

        if ($authoritativeFeedbackDraft && !$authoritativeFeedbackDraft->isDraft()) {
            // this isn't actually a draft (it'll be the same as $authoritativeFeedback)
            $authoritativeFeedbackDraft = null;
        }

        $resubmissionRequiredPublished = $authoritativeFeedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model
            ? $authoritativeFeedback->getResubmissionRequired()
            : false;
        $resubmissionRequiredDraft = $authoritativeFeedbackDraft instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model
            ? $authoritativeFeedbackDraft->getResubmissionRequired()
            : false;

        $authNormMarkPublished = $authoritativeFeedback ? $participant->getNormalisedMark(true) : null;
        $authNormMarkDraft = $authoritativeFeedbackDraft ? $participant->getNormalisedMark(false) : null;

        $marks = [
            'published' => [
                'norm'         => $authNormMarkPublished,
                'hr'           => $authoritativeFeedback ? $participant->getHumanReadableMark(true) : null,
                'hrInstructor' => $authoritativeFeedback ? $participant->getHumanReadableInstructorMark(true) : null,
                'comment'      => \Scrub::html($authoritativeFeedback?->getComment()),
                'resubmissionRequired' => $resubmissionRequiredPublished,
                'responseId'   => $authoritativeFeedback?->getId() ?? null,
            ],
            'draft' => [
                'norm'         => $authNormMarkDraft,
                'hr'           => $authoritativeFeedbackDraft ? $participant->getHumanReadableMark(false) : null,
                'hrInstructor' => $authoritativeFeedbackDraft ? $participant->getHumanReadableInstructorMark(false) : null,
                'comment'      => \Scrub::html($authoritativeFeedbackDraft?->getComment()),
                'resubmissionRequired' => $resubmissionRequiredDraft,
                'responseId'   => $authoritativeFeedbackDraft?->getId() ?? null,
            ],
        ];


        $this->response = $this->responseAsJson([
            'assessmentTitle' => $assessment->getTitle(),
            'assessmentType' => AssessmentType::objectToString($assessment),
            'workType' => $assessment->getWorkType()?->getName(),
            'maxMarks' => $maxMarks,
            'response' => $response,
            'feedback' => $feedback,
            'marks'    => $marks,
        ]);
    }

    /**
     * Process the learning plan for the custom participation
     *
     * @param IAssessable $assessment
     * @param Values      $postData
     * @return void
     * @throws MapperException
     */
    public function persistAssessmentParticipationLearningPlan(
        IAssessable $assessment,
        Web\Request\Values $postData,
    ): void {
        $returnUrl = $this->requestQuery->get('returnUrl');

        $planId = $postData['plan-id'];
        $planData[$planId] = [
            'id' => $planId,
            'name' =>  $postData['plan-name'],
            'description' => $postData['plan-desc'],
            'publish_date' =>  $postData['planPublishDate'],
            'open_date' => $postData['planOpenDate'],
            'due_date' => $postData['planDueDate'],
            'close_date' => $postData['planCloseDate'],
        ];
        // Note: in the returned collection
        //    - any plans CREATED by this method will be NEGATIVELY INDEXED (the object has a real ID though)
        //    - any plans UPDATED by this method will be ID INDEXED
        $plans = $this->savePlanData($assessment, $planData);

        $this->saveParticipationData($assessment, $plans, $postData['participant-include'] ?? [], $postData['participant-plan'] ?? []);

        $this->response = $this->redirectAfterPost($returnUrl ? Redirect::decode($returnUrl) : $this->getSession()->getHistory());
    }

    /**
     * To allow the participant to be added to the existing customisation
     *
     * @param IAssessable $assessment
     * @return void
     * @throws Deleted
     * @throws MapperException
     * @api   POST /learning/assessments/:assessmentId/participation/customisation/include
     *
     */
    public function postExistingCustomisationInclude(IAssessable $assessment): void
    {
        $post = $this->requestPost;
        $assessmentId = $post->get('assessmentId');
        $studentIds = $post->get('students') ?? [];
        $students = $this->getUserMapper()->findByColumn('id', $studentIds);
        try {
            $assessmentDataModel = $this->assessmentMapper->translateToDataModel($assessment);
            $participationHelper = (new Participant\ParticipationHelper($assessmentDataModel, $this->participantDataSource, $this->getUserMapper(), $this->assessmentMapper));

            if (null !== $assessmentId) {
                $customisedAssessment          = $this->getAssessmentMapper()->findById($assessmentId);
                $customisedAssessmentDataModel = $this->assessmentMapper->translateToDataModel(($customisedAssessment));
                $customisedParticipationHelper = (new Participant\ParticipationHelper($customisedAssessmentDataModel, $this->participantDataSource, $this->getUserMapper(), $this->assessmentMapper));
                foreach ($students as $student) {
                    $customisedParticipationHelper->include($student->getId(), $customisedAssessment->getDynamicPlan());
                    /* If it's new/cloned activity make sure we exclude it from the source assessment
                     */
                    $participationHelper->exclude($student->getId(), null);
                }
            } else {
                /** @var PlanModel $plan */
                $plan = $this->participantDataSource->getPlanMapper()->findById($post['planId']);
                foreach ($students as $student) {
                    $participationHelper->include($student->getId(), $plan);
                }
            }

            $this->response = $this->responseEmpty(201);
        } catch (\Exception $e) {
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($e->getCode());
            $this->response = $this->responseAsJson($problem->asArray());
        }
    }
}
