<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use DomainException;
use InvalidArgumentException;
use PDOException;
use RangeException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper;
use Schoolbox\Assessment\Participant as ParticipantModels;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Traversable;
use UnexpectedValueException;
use Alaress_Date_Format;


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
     * @param UserMapper             $userMapper
     * @param Error\Mapper           $errorMapper
     * @param MapperProxy            $assessmentMapper
     * @param Access                 $access
     * @param Transactor             $transactor
     * @param Participant\DataSource $participantDataSource
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        Participant\DataSource $participantDataSource
    ) {
        $this->setUserMapper($userMapper);
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapper($assessmentMapper);
        $this->setAccess($access);
        $this->setTransactor($transactor);
        $this->participantDataSource = $participantDataSource;
    }


    /**
     * Retrieve a list of assessments that exist, by context
     *
     * NOTE: this is a debug route that is only available to supers, so don't go referencing this route in twigs...
     * It shows the functionality of the common "assessment component" logic for any context
     *
     * @api GET /learning/{contextType}/{contextId}/assessments?user&readOnly
     * @since 165
     *
     * @param AssessmentContext $context
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
     * @throws Web\Controller\Exception
     * @throws UnexpectedValueException
     */
    public function postCreateAssessment(AssessmentContext $context)
    {
        try {
            // we need to perform some surgery on our "post" data before we send it through
            $post = $this->getRequest()->post->getArrayCopy();
            $post['context'] = $context;

            [$assessment, $defaultReturnUrl] = array_values($this->persistAssessment($this->getUser(), $post));

            $type       = AssessmentType::objectToString($assessment);
            $controller = $this->getDelegateController($type);

            $explicitReturnUrl = $this->getRequest()->post->get(Redirect::RETURN_URL);

            $this->redirect($controller->determineRedirect($assessment, $explicitReturnUrl, $defaultReturnUrl));
        } catch (DomainException $e) {
            $this->sendError('Could not save assessment.', [$e->getMessage()], 400, $e->getCode());
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
                $returnUrl = '/homepage/'.$context->getId();

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
                $returnUrl .= '#'.$context->getId();

            } elseif ($context instanceof UnitImplementation) {
                // Folder that this course is on
                $returnUrl = '/homepage/'.$context->getCourse()->getFolder()->getId();

            } else {
                // Back to base?
                $returnUrl = '/';
            }
        }
        $this->redirect($returnUrl);
    }

    /**
     * @param string $type
     * @return Controller
     */
    private function getDelegateController($type)
    {
        return $this->getDi()->get("controller:learningAssessment/$type");
    }


    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @since 165
     *
     * @param AssessmentContext $context
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
            } catch (ServiceNotFound $e) {
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
            ['id' => 'due-work', 'value' => AssessmentType::TYPE_DUE_WORK, 'label' => 'Due Work',   'title' => 'Create an assessable item for students that can accept submissions'],
            ['id' => 'task',     'value' => AssessmentType::TYPE_TASK,     'label' => 'Task',       'title'=> 'A simple activity that will track student completion'],
            ['id' => 'quiz',     'value' => AssessmentType::TYPE_QUIZ,     'label' => 'Quiz',       'title'=> 'Ask a series of questions with automatic marking'],
        ];
        // TODO, 'types' should just be the 3 objects, and twig should do the rest
        // TODO (see icon macro for 'id', assessment.typeName for 'label', types.objectToString($assessment) for 'value')

        // Ensure we don't provide an option to spawn a Project inside a Project context
        if (!$context instanceof Project) {
            $item = ['id' => 'project',  'value' => AssessmentType::TYPE_PROJECT,  'label' => 'Project', 'title'=>'Setup a container for multiple assessments that are related'];
            array_unshift($data['types'], $item);
        }

        $data['contextType'] = AssessmentContextType::objectToString($context) . 's';
        $data['context']     = $context;

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
     * @param IAssessable       $assessment
     * @return array
     * @throws DomainException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws \LogicException
     */
    protected function persistAssessment(User $user, $postData, IAssessable $assessment = null): array
    {
        /* If an assessment couldn't be produced, bail out
         * and let mage see the errors set by $this->sendError
         *
         * Controller must return early for that to happen
         */
        try {
            // Create a notifier proxy so that I may notify about assessment changes
            $notifier = $this->assessmentMapper->getNotifier();
            $before   = $notifier->dehydrate($assessment);

            /* Figure out what kind of assessment was chosen
             * and spawn a new instance of it to manipulate
             */
            $assessmentType = $postData['assessmentType'];
            $assessmentFactory = new Factory($user, $postData, $assessmentType);
            $assessment = $assessmentFactory->produce($assessment);

            $transactionSummary = $this->getTransactor()->transact($assessment);
            $assessmentId = $assessment->getId();

            // assessment is saved; persist plans

            // Note: in the returned collection
            //    - any plans CREATED by this method will be NEGATIVELY INDEXED (the object has a real ID though)
            //    - any plans UPDATED by this method will be ID INDEXED
            $plans = $this->savePlanData($assessment, $postData['plans'] ?? []);

            $this->saveParticipationData($plans, $postData['participant-include'] ?? [], $postData['participant-plan'] ?? []);

            // Retrieve assessment, as I need the assessment's participation
            $assessment = $this->assessmentMapper->findByIdForReview($assessment->getId());

            // And send notifications
            $notifier->notify($assessment, $this->getUser(), $before);
        }
        catch (Mapper\Exception|RangeException|InvalidArgumentException|UnexpectedValueException|PDOException $e) {
            // cast
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }

        return $transactionSummary;
    }


    /**
     * @param GenericCollection|ParticipantModels\Plan\Model[] $plans
     * @param array                                            $inclusionFlags
     * @param array                                            $userPlanIndices
     * @throws DomainException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function saveParticipationData(GenericCollection $plans, array $inclusionFlags, array $userPlanIndices)
    {
        $partMap = $this->participantDataSource->getParticipantMapper();

        $planIds = $plans->getPropertyValues('id');

        // plans are saved; persistipation
        foreach ($inclusionFlags as $userId => $userIncluded) {
            $existingParticipation = $partMap->findByColumnList([
                'participant_plan_id' => $planIds,
                'user_id'             => $userId
            ]);

            if ($userIncluded) {
                // look up the plan
                $userPlan = $plans[$userPlanIndices[$userId]];

                if (!$userPlan) {
                    throw new DomainException("User {$userId} was assigned index {$userPlanIndices[$userId]}, but there's no plan with that index (valid plan indices were " . json_encode($plans->keys()) . ')');
                }

                // generate a record if there is ALREADY existing participation, OR the plan is NOT dynamic
                if ($existingParticipation->count() || !$userPlan->isDynamic()) {
                    // this action needs to enforce the constraint of a user being in 0..1 plan
                    // TODO atomicity!!! :'(
                    $partMap->delete($existingParticipation);
                    $partMap->save($this->makeAParticipation($userId, $userPlan));
                }
            } else {
                if ($existingParticipation->count() === 0) {
                    // we need to generate a record so we have *something* to exclude
                    $existingParticipation = $partMap->createCollection([
                        $this->makeAParticipation($userId, $plans->filterBy('isDynamic', true)->first())
                    ]);
                }

                foreach ($existingParticipation as $record) {
                    $record->setExcludedAt(\Alaress_Date_Format::now());
                }

                $partMap->save($existingParticipation);
            }
        }
    }


    /**
     * @param int $userId
     * @param ParticipantModels\Plan\Model $plan
     * @return ParticipantModels\Model
     */
    private function makeAParticipation($userId, $plan)
    {
        $partMap = $this->participantDataSource->getParticipantMapper();
        $userMap = $this->userMapper;

        return $partMap->createModel()
            ->setUser($userMap->createModel($userId))
            ->setPlan($plan);
    }

    /**
     * The return of this method will be a UNION of all plans that exist for the given assessment ID
     * and the new plans specified by $postPlans. New plans will be persisted to the database (and thus have a real
     * ID), but in the returned collection, they will be NEGATIVELY INDEXED, as given in $postPlans.
     *
     * @param IAssessable $assessment
     * @param array       $postPlans
     * @return GenericCollection|ParticipantModels\Plan\Model[]
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws Mapper\Exception
     * @throws InvalidArgumentException
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
            if (($assessment instanceof DueWork\Model && $assessment->getAcceptOnline() == 0) || $assessment instanceof Task\Model ) {
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

                        $planData[$dateField] = \Alaress_Date_Format::newLocal($planData[$dateField])->getDatabase();
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
     * If XHR, return the redirect within JSON, otherwise just tell the browser to do the redirect
     *
     * @param $url
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
     * @return Transactor
     */
    protected function getTransactor()
    {
        return $this->transactor;
    }


    /**
     * @param Transactor $transactor
     * @return static
     */
    protected function setTransactor($transactor)
    {
        $this->transactor = $transactor;
        return $this;
    }

}
