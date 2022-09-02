<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Module\Registry;
use Alaress\Web;
use Aura\Router\Exception\RouteNotFound;
use Aura\Web\Exception\InvalidStatusCode;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Feedback\Visibility as FeedbackVisibility;
use Schoolbox\Learning\Assessment\Seb\Payload;
use Schoolbox\Learning\Assessment\Seb\Payload as SebPayload;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper;
use Schoolbox\Assessment\Participant\Model as ParticipantModel;
use Schoolbox\Assessment\Participant\Plan\Model as PlanModel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Traversable;
use UnexpectedValueException;
use Alaress_Date_Format;

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
     * @var Transactor
     */
    private $transactor;

    /**
     * @var Participant\DataSource
     */
    protected $participantDataSource;

    /**
     * @var UnitSpecificationMapper
     */
    protected $unitSpecificationMapper;

    /**
     * @var Collection<GenericCollection>|null $existingParticipationPerUser
     */
    private $existingParticipationPerUser;


    /**
     * @var BreadcrumbFactory
     */
    protected BreadcrumbFactory $bakery;


    /**
     * @param UserMapper              $userMapper
     * @param Error\Mapper            $errorMapper
     * @param MapperProxy             $assessmentMapper
     * @param Access                  $access
     * @param Transactor              $transactor
     * @param Participant\DataSource  $participantDataSource
     * @param UnitSpecificationMapper $unitSpecificationMapper
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        Participant\DataSource $participantDataSource,
        UnitSpecificationMapper $unitSpecificationMapper
    ) {
        $this->setUserMapper($userMapper);
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapper($assessmentMapper);
        $this->setAccess($access);
        $this->transactor              = $transactor;
        $this->participantDataSource   = $participantDataSource;
        $this->unitSpecificationMapper = $unitSpecificationMapper;
    }


    /**
     * Offer a SEB Safe Exam Browser download screen
     *
     * @api GET /learning/assessments/{assessmentId}/seb
     * @since 21.0.7
     */
    public function getSebDownload(IAssessable $assessment): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/sebDownload.twig', [
            'assessmentId' => $assessment->getId(),
            'assessment' => $assessment,
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([
                $assessment->getTitle(),
                '',
            ]),
        ]));
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
            $this->getRequest()->server->get('HTTP_HOST'),
            $this->router
        );

        $contents = $sebPayload->generate($assessment, $this->getUser());

        /* Send as a downloadable file
         */
        $headers  = $this->getResponse()->headers;

        $headers->set('Pragma', 'no-cache');
        $headers->set('Cache-Control', 'private, max-age=1, no-transform');
        $headers->set('Content-Type', 'application/seb');
        $headers->set('Content-Length', strlen($contents));
        $headers->set('Content-Disposition', "attachment; filename=\"{$assessment->getId()}.seb\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $contents,
        ]);
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
        if ($this->request->query->get(Payload::SEB_KEY)) {
            $this->session->set(Payload::SEB_KEY, $this->request->query->get(Payload::SEB_KEY));
        }
        return (new Seb\ValidSebCheck())->isSatisfiedBy(
            $this->request->server->getArrayCopy(),
            /* Don't worry about the Browser Exam Key for now
             * We need to generate some platform-specific crypto for it
             */
            'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
            /* Generate a config key hash from the xml that guards this assessment url
             */
            (new SebPayload(
                $this->getConfigLoader(),
                $this->request->server->get('HTTP_HOST'),
                $this->router
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

        $q = $this->getRequest()->query;

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

        $assessments = $this->getAssessmentMapper()->findAllByContext($context, ['hydrateParticipants' => true ]);

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
            '_is_xhr'         => $this->getRequest()->isXhr(),
        ];

        $this->tpl('learningAssessment', 'component', $data);
    }


    /**
     * Create a new assessment in the given context
     *
     * @api   POST /learning/{:contextType}/{:contextId}/assessments
     * @since 165
     *
     * @param AssessmentContext $context
     * @throws LogicException
     * @throws Mapper\DefinitionException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws Web\Controller\Exception
     */
    public function postCreateAssessment(AssessmentContext $context)
    {
        try {
            // we need to perform some surgery on our "post" data before we send it through
            $post = $this->getRequest()->post->getArrayCopy();
            $post['context'] = $context;

            $assessmentInfo = $this->persistAssessment($this->getUser(), $post);

            $type       = AssessmentType::objectToString($assessmentInfo['assessment']);
            $controller = $this->getDelegateController($type);

            $explicitReturnUrl = $this->getRequest()->post->get(Redirect::RETURN_URL);

            $this->redirect($controller->determineRedirect(
                $assessmentInfo['assessment'],
                $explicitReturnUrl,
                $assessmentInfo[Redirect::RETURN_URL],
                $assessmentInfo['relocated']
            ));
        } catch (DomainException $e) {
            $this->sendError('Could not save assessment. ', [$e->getMessage()], 400, $e->getCode());
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
        foreach ($this->getRequest()->post->get('id', []) as $assessmentId) {
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
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            if ($context instanceof Folder) {
                // Folder homepage
                $returnUrl = '/homepage/' . $context->getId();
            } elseif ($context instanceof Project) {
                // Project status page
                $returnUrl = $this->getRouter()->generate(
                    'learningAssessment.getStatus',
                    ['assessmentId' => $context->getId()]
                );
            } elseif ($context instanceof UnitSpecification) {
                // Course modify page for this unit
                $returnUrl = $this->getRouter()->generate(
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
     * @throws InvalidStatusCode
     * @throws MapperException
     */
    public function updateWeight(IAssessable $assessment): void
    {
        $weight = (float) $this->getRequest()->post['weight'];
        if ($weight < 0) {
            $this->getResponse()->status->setCode(500);
            $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'Invalid weight value']));
            return;
        }
        $assessment->setWeight($weight);
        $this->getAssessmentMapper()->save($assessment, $this->getUser());
        $this->getResponse()->content->set($this->buildJsonOutput(['message' => 'Loaded successfully']));
    }

    /**
     * Updates assessment's visibility
     *
     * @api   POST /learning/visibility/:assessmentId
     *
     * @param IAssessable $assessment
     * @throws InvalidStatusCode
     * @throws MapperException
     */
    public function updateVisibility(IAssessable $assessment): void
    {
        $visibility            = (int)$this->getRequest()->post['visibility'];
        $availableVisibilities = FeedbackVisibility::getAvailableVisibilities($this->getConfigLoader()->get('submission_visibility_max'));
        if (!in_array($visibility, $availableVisibilities)) {
            $this->getResponse()->status->setCode(500);
            $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'Invalid visibility']));
            return;
        }

        // get assessment before the visibility change for notification
        $previous = $this->getAssessmentMapper()->getNotifier()->dehydrate($assessment);
        $assessment->setMarkVisibility($visibility);
        $this->getAssessmentMapper()->save($assessment, $this->getUser());
        // triggering notification explicitly, to notify marked, if necessary
        $this->getAssessmentMapper()->getNotifier()->notify($assessment, $this->getUser(), $previous);

        $this->getResponse()->content->set($this->buildJsonOutput(['message' => 'Loaded successfully']));
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
        if ($type = $this->getRequest()->query->get('type')) {
            try {
                // delegate to the appropriate sub-controller
                $delegate = $this->getDelegateController($type);
                $delegate->getCreateAssessmentForm($context);
                $this->setResponse($delegate->getResponse());
                return;
            } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
                // if the client enters an invalid type: ignore
                // and allow passthru to the "unselected" section
            }
        }

        // Did the caller request a returnUrl?
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);

        if ($returnUrl) {
            $data[Redirect::RETURN_URL] = $returnUrl;
        }

        // show the generic first-half of the form
        $data['types'] = [
            [
                'id'    => 'due-work',
                'value' => AssessmentType::TYPE_DUE_WORK,
                'label' => 'Due Work',
                'icon'  => 'due-work',
                'title' => 'Create an assessable item for students that can accept submissions',
            ],
            [
                'id'    => 'task',
                'value' => AssessmentType::TYPE_TASK,
                'label' => 'Task',
                'icon'  => 'task',
                'title' => 'A simple activity that will track student completion',
            ],
            [
                'id'    => 'quiz',
                'value' => AssessmentType::TYPE_QUIZ,
                'label' => 'Quiz',
                'icon'  => 'quiz',
                'title' => 'Ask a series of questions with automatic marking',
            ],
        ];
        if ($this->getConfigLoader()->get('lti_assessments_enable')) {
            $data['types'][] = [
                'id'    => 'lti',
                'value' => AssessmentType::TYPE_LTI_ASSESSMENT,
                'label' => '3rd Party Assessment',
                'icon'  => 'lti',
                'title' => 'Connect an assessment provided by an LTI tool',
            ];
        }
        // TODO, 'types' should just be the 3 objects, and twig should do the rest
        // TODO (see icon macro for 'id', assessment.typeName for 'label', types.objectToString($assessment) for 'value')

        // Ensure we don't provide an option to spawn a Project inside a Project context
        if (!$context instanceof Project) {
            $item = [
                'id'    => 'project',
                'value' => AssessmentType::TYPE_PROJECT,
                'label' => 'Project',
                'icon'  => 'project',
                'title' => 'Setup a container for multiple assessments that are related',
            ];
            array_unshift($data['types'], $item);
        }

        $data['contextType'] = AssessmentContextType::objectToString($context) . 's';
        $data['context']     = $context;
        $data['breadcrumbs'] = [
            ['Learning', $this->router->generate('learning.getClasses')],
            [$context->getFolder()->getTitle(), "/homepage/{$context->getFolder()->getId()}"],
        ];
        if ($context instanceof Project) {
            $data['breadcrumbs'][] = [
                $context->getTitle(),
                $this->router->generate(
                    'learningAssessment.getStatus',
                    [
                        'assessment'   => $context,
                        'assessmentId' => $context->getId(),
                    ]
                ),
            ];
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/create.twig', $data));
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
     */
    protected function persistAssessment(User $user, $postData, IAssessable $assessment = null): array
    {
        /* If an assessment couldn't be produced, bail out
         * and let mage see the errors set by $this->sendError
         *
         * Controller must return early for that to happen
         */
        $db = $this->getUserMapper()->getDatabase();
        try {
            $db->beginTransaction();
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

            $assessmentFactory = new Factory($user, $postData, $postData['assessmentType'], $originalAssessment);
            $assessment        = $assessmentFactory->produce($assessment);

            $transactionSummary = $this->transactor->transact($assessment, $this->getUser(), $before, $originalAssessment);

            // assessment is saved; persist plans

            // Note: in the returned collection
            //    - any plans CREATED by this method will be NEGATIVELY INDEXED (the object has a real ID though)
            //    - any plans UPDATED by this method will be ID INDEXED
            $plans = $this->savePlanData($assessment, $postData['plans'] ?? []);

            $this->saveParticipationData($plans, $postData['participant-include'] ?? [], $postData['participant-plan'] ?? []);
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

        return $transactionSummary;
    }


    /**
     * @param GenericCollection|PlanModel[] $plans
     * @param array                         $inclusionFlags  userId => 0 ... excluded, 1 ... included
     * @param array                         $userPlanIndices userId => planId
     * @throws MapperException
     */
    private function saveParticipationData(GenericCollection $plans, array $inclusionFlags, array $userPlanIndices): void
    {
        $participantMapper = $this->participantDataSource->getParticipantMapper();

        // plans are saved; persistipation
        foreach ($inclusionFlags as $userId => $userIncluded) {
            /** @var ParticipantModel|null $existingParticipation */
            $existingParticipation = $this->getExistingParticipation($plans, $userId);
            /** @var PlanModel $userPlan */
            $userPlan = $plans[$userPlanIndices[$userId]];
            if ($userIncluded) {
                if (!$userPlan) {
                    throw new DomainException("User {$userId} was assigned index {$userPlanIndices[$userId]}, but there's no plan with that index (valid plan indices were " . json_encode($plans->keys()) . ')');
                }

                // generate a record if there is ALREADY existing participation, OR the plan is NOT dynamic
                if (!$existingParticipation && $userPlan->isDynamic()) {
                    continue;
                }

                if ($existingParticipation && !$existingParticipation->isExcluded() && ($existingParticipation->getPlan()->getId() === $userPlan->getId())) {
                    // nothing changed
                    continue;
                }

                // delete existing participation
                if ($existingParticipation) {
                    $restoredCount             = 0;
                    $keepExistingParticipation = false;

                    if ($existingParticipation->isExcluded()) {
                        $restoredCount = $participantMapper->restoreResponsesAndFeedbacks($existingParticipation);
                        // if new plan is dynamic and if recovered responses and feedbacks exist, keep the participant data
                        if ($userPlan->isDynamic() && $restoredCount > 0) {
                            $existingParticipation->setExcludedAt(null);
                            $participantMapper->save($existingParticipation);
                            $keepExistingParticipation = true;
                        }
                    }

                    if (!$keepExistingParticipation) {
                        $participantMapper->delete($existingParticipation);
                    }
                }

                if (!$userPlan->isDynamic()) {
                    // we need to generate a record so we have *something* to exclude
                    $newParticipation = $participantMapper->createModel();
                    $newParticipation->setUser($this->userMapper->createModel($userId))
                                     ->setPlan($userPlan);
                    $participantMapper->save($newParticipation);
                }
            } else {
                // excluded
                if ($existingParticipation && $existingParticipation->isExcluded() && $existingParticipation->getPlan()->getId() === $userPlan->getId()) {
                    // nothing changed
                    continue;
                }
                if ($existingParticipation) {
                    if (!$existingParticipation->isExcluded()) {
                        $participantMapper->softDeleteResponsesAndFeedbacks($existingParticipation);
                    }
                    $participantMapper->delete($existingParticipation);
                }

                // we need to generate a record so we have *something* to exclude
                $newParticipation = $participantMapper->createModel();
                $newParticipation->setUser($this->userMapper->createModel($userId))
                                 ->setPlan($userPlan)
                                 ->setExcludedAt(\Alaress_Date_Format::now());
                $participantMapper->save($newParticipation);
            }
        }
    }


    /**
     * Gets existing participation
     * @param GenericCollection $plans
     * @param int               $userId
     * @return ParticipantModel|null
     * @throws DomainException
     * @throws Mapper\DefinitionException
     */
    private function getExistingParticipation(GenericCollection $plans, int $userId): ?ParticipantModel
    {
        // If haven't got participation data, get it
        if (!$this->existingParticipationPerUser) {
            /* Find Plans are associated with an assessment
             *
             * Multiplayer schoolbox allows $plans to be different upon saving
             * so you can't use it as a source of truth - $plans ids in one tab
             * is different to $plans ids in another tab, allowing a misunderstanding
             * to occur that inadvertently lets a person have multiple plans
             */

            /* For each of those plans, find out if $userId is associated with it
             * If it is, then this person already has a plan - index them with a
             * (user id, plan id) tuple. This is the actual truth about which user
             * has a plan in a given assessment; you cannot trust the GenericCollection
             * $plans to contain the source of truth
             */





            $participantMapper = $this->participantDataSource->getParticipantMapper();
            $planIds           = $plans->getPropertyValues('id');

            $allExistingParticipation = $participantMapper->findByColumnList([
                'participant_plan_id' => $planIds,
            ], ['cache' => false]);

            // userId => plan (should only have one plan per user)
            $this->existingParticipationPerUser = $allExistingParticipation->groupByCallback(
                function (ParticipantModel $model) {
                    return $model->getUser()->getId();
                }
            );
        }

        // Check to see whether only one participation exists for the current user and get it
        if ($this->existingParticipationPerUser->offsetExists($userId)) {
            /** @var GenericCollection $existingParticipationCollection */
            $existingParticipationCollection = $this->existingParticipationPerUser->getOffset($userId);
            if ($existingParticipationCollection->count() > 1) {
                throw new DomainException('More than one existing participation found for an user');
            }
            if ($existingParticipationCollection->count() === 1) {
                return $existingParticipationCollection->first();
            }
        }
        return null;
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
        $dateFields = [ 'publish_date',  'open_date', 'due_date', 'close_date', 'created_at', 'updated_at' ];

        $planMap = $this->participantDataSource->getPlanMapper();

        // preload the plans list with the dyna-plan
        $plans = $planMap->findByColumnList([ 'is_dynamic' => true, 'assessment_id' => $assessmentId ])->indexBy('id');

        // obtain this reference for later
        $dynaPlan = $plans->first();
        // within $postPlans, any positive index is the ID of an existing plan; any negative index is a new plan
        foreach ($postPlans as $planId => $planData) {
            // if planId == 0, it's either the dyna-plan or invalid data, but ignorable either way

            //Handle assessments that do no accept submissions:
            if (($assessment instanceof DueWork\Model && $assessment->getAcceptOnline() == 0) || $assessment instanceof Task\Model) {
                $planData['open_date'] = $planData['publish_date'];
                $planData['close_date'] = $planData['due_date'];
            }

            if ($planId > 0) {
                // existing plan updates
                $plans[$planId] = $planMap
                    ->createModel($planId)
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
                $planData['is_dynamic']    = false;
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
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([ Redirect::RETURN_URL => $url ]));
        } else {
            $this->getResponse()->redirect->afterPost($url);
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
}
