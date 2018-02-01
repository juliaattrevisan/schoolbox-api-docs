<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use OutOfBoundsException;
use Schoolbox\Learning\Authorizer\Rule\AssessmentNotDeleted;
use Schoolbox\Learning\Authorizer\Rule\CanRespondToAssessment;
use Schoolbox\Learning\Authorizer\Rule\CanSeeParticipation;
use Schoolbox\Learning\Authorizer\Rule\IsAssessmentOpen;
use Schoolbox\Learning\Authorizer\Rule\ValidAssessmentType;
use Schoolbox\Learning\Authorizer\Rule\IsDeletionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsImplementation;
use Schoolbox\Learning\Authorizer\Rule\IsModifyUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsParticipant;
use Schoolbox\Learning\Authorizer\Rule\ParticipantHasNoInteractions;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Assessment\Course\Task\CompletionMapper as TaskResponseMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseDataSource;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use UnexpectedValueException;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Application\Exception as ApplicationException;
use DomainException;
use InvalidArgumentException;
use LogicException;


class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessment';

    /**
     * By default, this will point to the list controller.
     * If a preload successfully loads an Assessment, this value will
     * be mutated to the appropriate controller class name.
     *
     * @var string
     */
    private $controllerClass = ListController::class;

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     * @throws NotFound
     * @throws InvalidPath
     */
    protected function registerRoutes()
    {
        // preloaders to use
        $preloadContext                = function ($params) { return $this->preloadContext($params); };
        $preloadAssessment             = function ($params) { return $this->preloadAssessment($params, false); };
        $preloadAssessmentWithExcluded = function ($params) { return $this->preloadAssessment($params, true); };
        $preloadParticipant            = function ($params) { return $this->preloadParticipant($params); };

        // rules in use
        $isSuper                  = $this->getAuthRule('isSuperuser');
        $loggedIn                 = $this->getAuthRule('isLoggedIn');
        $canWrite                 = $this->buildAuthRuleHomepageAccess(UserAccess::ACCESS_WRITE);
        $canViewSummary           = $this->buildAuthRuleCanViewSummary();
        $canSubmit                = $this->buildAuthRuleCanSubmit();
        $canViewStatus            = $this->buildAuthRuleCanViewStatus();
        $canAckSubmission         = $this->buildAuthRuleCanAcknowledgeSubmission();

        $canDelete = new Rule\Any(
            $isSuper,
            new Rule\All(
                $canWrite,
                new IsDeletionUnLocked(new LockedChecker($this->getTermMapper()))
            )
        );

        $canModify = new Rule\Any(
            $isSuper,
            new Rule\All(
                $canWrite,
                new IsModifyUnLocked(new LockedChecker($this->getTermMapper()))
            )
        );

        // pluralise for REST
        $contextTypes = AssessmentContextType::getStringValuesAsRegex(function ($name) { return $name.'s'; });

        // tokenised route chunks
        $assRoute     = '/assessments/{assessmentId:\d+}';
        $assListRoute = "/{contextType:$contextTypes}/{contextId:\\d+}/assessments";
        $studentParam = '{studentId:\d+}';


        // register the routes
        $this->register('GET',   "$assListRoute",                    'getAssessmentsByContext',  $isSuper,          $preloadContext);
        $this->register('POST',  "$assListRoute",                    'postCreateAssessment',     $canWrite,         $preloadContext); // a || c
        $this->register('POST',  "$assListRoute/reorder",            'postReorderAssessments',   $canWrite,         $preloadContext); // a || c
        $this->register('GET',   "$assListRoute/new",                'getCreateAssessmentForm',  $canWrite,         $preloadContext); // a || c

        $this->register('GET',   "$assRoute",                        'getAssessment',            $loggedIn,         $preloadAssessment);
        $this->register('POST',  "$assRoute",                        'postUpdateAssessment',     $canWrite,         $preloadAssessment); // a || c
        $this->register('PATCH', "$assRoute",                        'patchUpdateAssessment',    $canWrite,         $preloadAssessment); // a || c
        $this->register('GET',   "$assRoute/modify",                 'getUpdateAssessmentForm',  $canModify,        $preloadAssessmentWithExcluded); // a || c
        $this->register('POST',  "$assRoute/delete",                 'postDeleteAssessment',     $canDelete,        $preloadAssessment); // a || c
        $this->register('GET',   "$assRoute/status",                 'getStatus',                $canViewStatus,    $preloadAssessment); // a || c
        $this->register('GET',   "$assRoute/overview",               'getOverview',              $canWrite,         $preloadAssessment); // a || c
        $this->register('GET',   "$assRoute/overview/export",        'downloadOverview',         $canWrite,         $preloadAssessment); // a || c
        $this->register('GET',   "$assRoute/submissions/download",   'downloadSubmissions',      $canWrite,         $preloadAssessment); // a || c

        $this->register('GET',   "$assRoute/$studentParam",          'getSummary',               $canViewSummary,   $preloadParticipant); // s || p
        $this->register('GET',   "$assRoute/preview",                'getPreview',               $canWrite,         $preloadAssessment);  // s || p
        $this->register('GET',   "$assRoute/$studentParam/submit",   'getCreateResponseForm',    $canSubmit,        $preloadParticipant); // p
        $this->register('POST',  "$assRoute/$studentParam/submit",   'postCreateResponse',       $canSubmit,        $preloadParticipant); // p
        $this->register('POST',  "$assRoute/$studentParam/received", 'postStatusAcknowledge',    $canAckSubmission, $preloadParticipant); // p
        $this->register('POST',  "$assRoute/$studentParam/ack",      'postPendingAcknowledge',   $canAckSubmission, $preloadParticipant); // p
        $this->register('POST',  "$assRoute/$studentParam/ackSelf",  'postSelfAcknowledge',      $canAckSubmission, $preloadParticipant); // p
    }


    /**
     * Set the name of the controller to execute, based on the given assessment
     *
     * @param IAssessable $a
     * @return void
     * @throws UnexpectedValueException If unable to recognise IAssessable object
     */
    public function setControllerFromAssessment(IAssessable $a)
    {
        // TODO centralise this into a factory
        $map = [
            AssessmentType::TYPE_DUE_WORK => Assessment\DueWork\Controller::class,
            AssessmentType::TYPE_PROJECT  => Assessment\Project\Controller::class,
            AssessmentType::TYPE_QUIZ     => Assessment\Quiz\Controller::class,
            AssessmentType::TYPE_TASK     => Assessment\Task\Controller::class,
        ];

        // set the controller class for later
        $this->controllerClass = $map[AssessmentType::objectToString($a)];
    }


    /**
     * Convert route params into a participant object
     *
     * @param array $params
     * @return array
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws ApplicationException\Exception If assessment is not found
     * @throws NotFound
     * @throws InvalidPath
     */
    protected function preloadParticipant(array $params)
    {
        try {
            // obtain the assessment from our other preload
            $student     = $this->getUserMapper()->findById($params['studentId']);
            $assessment  = $this->preloadAssessment($params, false, $student)['assessment'];

            $access = $this->getAccess()->getFolderAccess($student, $assessment->getContext()->getFolder()->getId());

            /* Allow the currently logged in user to access the student's Assessment Summary
             *
             * This allows write-accessors to view a typically read-access page ie. when a teacher
             * wants to preview a task's summary screen (see SA-2193)
             *
             * This pretends the teacher is a participant
             */
            if ($access >= UserAccess::ACCESS_WRITE) {
                $plan = new Plan($assessment);
                $assessment->getPlans()->add($plan);
                $participant = new Participant($student, $plan);
                $plan->getParticipants()[$student->getId()] = $participant;

                /* Suck down all the responses from $assessment and put them into $participant
                 */
                /** @var Participant $currentParticipant */
                foreach ($assessment->getAllParticipants() as $currentParticipant) {
                    if ($currentParticipant->getUser()->getId() == $student->getId()) {

                        $participant->setAllResponses($currentParticipant->getAllResponses());

                        break;
                    }
                }
            }
            else {
                /** @var IAssessable $assessment */
                $participant = $assessment->getParticipant($student);
            }
        } catch (UnexpectedValueException $e) {
            // Student isn't a participant
            throw new InvalidPath();
        } catch (OutOfBoundsException $e) {
            // given user/assessment combination is invalid: not a participant
            throw new NotFound();

        } catch (MapperException $e) {
            // either assessment or user was not found
            throw new NotFound();
        }

        return [
            'participant' => $participant,
            'student'     => $participant->getUser() //IsParentOfStudent accesses this
        ];
    }

    /**
     * Convert route params into a context object
     *
     * @param $params
     * @return array
     */
    protected function preloadContext(array $params)
    {
        try {
            $contextFactory = new Assessment\DataModel\Context\Factory(
                $this->getFolderMapper(),
                $this->getUnitImplementationMapper(),
                $this->getAssessmentMapperProxy(),
                $this->getUnitSpecificationMapper()
            );
            $c = $contextFactory->produce($params['contextType'],$params['contextId']);
        } catch (MapperException\Deleted $e) {
            $c = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'context' => $c
        ];
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array     $params
     * @param bool      $includeExcluded Should we load excluded participations?
     * @param null|User $student If provided, only load this student's participation
     * @return array
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws UnexpectedValueException If unable to derive project participant collections
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws ApplicationException\Exception If assessment is not found
     */
    protected function preloadAssessment(array $params, bool $includeExcluded, User $student = null)
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $includeExcluded, $student);
        } catch (MapperException\Deleted $e){
            $assessment = $e->getDeletedObject();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (MapperException $e) {
            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        // set the controller
        $this->setControllerFromAssessment($assessment);

        return [
            'assessment' => $assessment
        ];
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        // NOTE the pointer magic to make things more concise

        // base list controller
        $lc =& $injector->params[ListController::class];
        $lc['userMapper']  = $this->getMapperFactory()->getLazy(UserMapper::class);
        $lc['errorMapper'] = $this->getMapperFactory()->getLazy(ErrorMapper::class);
        $lc['access']      = $this->getMapperFactory()->getLazy(Access::class);
        $lc['transactor']  = $injector->lazyGet('transactor');
        $lc['participantDataSource'] = $injector->lazyGet('participantDataSource');

        // abstract controller
        $ac =& $injector->params[Controller::class];
        $ac['bakery'] = $injector->lazyNew(Breadcrumb\Factory::class, [
            'router' => $injector->lazyGet('router')
        ]);
        $ac['workTypeMapper']        = $this->getMapperFactory()->getLazy(WorkTypeMapper::class);
        $ac['termMapper']            = $this->getMapperFactory()->getLazy(Term\Mapper::class);

        // due work specific
        $dc =& $injector->params[Assessment\DueWork\Controller::class];
        $dc['rubricMapper']             = $this->getMapperFactory()->getLazy(SubmissionBox\Rubric\RubricMapper::class);
        $dc['resultSetMapper']          = $this->getMapperFactory()->getLazy(SubmissionBox\Rubric\ResultMapper::class);

        // TODO get this out of DI
        $dc['dueWorkResponseMapper']    = $injector->lazyNew(
            DueWorkResponseMapper::class,
            ['mapper' => $this->getMapperFactory()->getLazy(StudentSubmissionMapper::class)]
        );
        $dc['participantMapper'] = $injector->lazyNew(ParticipantDataSource::class);
        $dc['submissionFileMapper'] = $injector->lazyNew(SingleFile\Mapper::class);
        $dc['kamiConnector'] = $injector->lazyGet('kamiConnector');
        $dc['kamiMapper'] = $this->getMapperFactory()->getLazy(Kami\Mapper::class);

        // project specific
        // $pc =& $injector->params[Assessment\Project\Controller::class];

        // quiz specific
        $qc =& $injector->params[Assessment\Quiz\Controller::class];
        $qc['quizMapper']        = $this->getMapperFactory()->getLazy(QuizMapper::class);
        $qc['quizSectionMapper'] = $this->getMapperFactory()->getLazy(QuizSectionMapper::class);
        $qc['quizAttemptMapper'] = $this->getMapperFactory()->getLazy(QuizAttemptMapper::class);

        // task specific
        $tc =& $injector->params[Assessment\Task\Controller::class];
        $tc['taskResponseDataSource'] = $injector->lazyNew(
            TaskResponseDataSource::class,
            ['mapper' => $this->getMapperFactory()->getLazy(TaskResponseMapper::class)]
        );

    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $basename = 'controller:' . $this->basename;

        // dynamically choose your poison
        $injector->set($basename, $injector->lazyNew($this->controllerClass));

        $d = AssessmentType::TYPE_DUE_WORK;
        $p = AssessmentType::TYPE_PROJECT;
        $q = AssessmentType::TYPE_QUIZ;
        $t = AssessmentType::TYPE_TASK;

        // declare all the options anyway
        $injector->set("$basename/$d", $injector->lazyNew(Assessment\DueWork\Controller::class));
        $injector->set("$basename/$p", $injector->lazyNew(Assessment\Project\Controller::class));
        $injector->set("$basename/$q", $injector->lazyNew(Assessment\Quiz\Controller::class));
        $injector->set("$basename/$t", $injector->lazyNew(Assessment\Task\Controller::class));
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return Mapper|Term\Mapper
     * @throws \InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }


    /**
     * @return Mapper|ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return object|AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return Mapper|UnitImplementationMapper
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(UnitImplementationMapper::class);
    }

    /**
     * @return Mapper|UnitSpecificationMapper
     */
    protected function getUnitSpecificationMapper()
    {
        return $this->getMapperFactory()->get(UnitSpecificationMapper::class);
    }

    /**
     * @return Mapper|StudentSubmissionMapper
     */
    protected function getStudentSubmissionMapper()
    {
        return $this->getMapperFactory()->get(StudentSubmissionMapper::class);
    }

    /**
     * @return Mapper|SubmissionReturnMapper
     */
    protected function getSubmissionReturnMapper()
    {
        return $this->getMapperFactory()->get(SubmissionReturnMapper::class);
    }

    /**
     * @return Mapper|StorageMapper
     */
    protected function getStorageMapper()
    {
        return $this->getMapperFactory()->get(StorageMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * Rules in order of preference:
     * - SU can submit
     * - staff/students cannot if due in archived term (ie 'interaction locked')
     * - staff must have WRITE+ perm to folder
     * - students cannot if not open
     * - Students cannot submit if reached response limit
     * - students cannot if not participant, or a different student to that indicated in URL
     *
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException If required object not found in route params
     * @throws NotFound
     */
    private function buildAuthRuleCanSubmit()
    {
        return new Rule\Any(
            new Rule\All(
                new Rule\IsSuperuser(),
                new CanRespondToAssessment($this->getAccess())
            ),
            new Rule\All(
                $this->buildAuthRuleHomepageAccess(UserAccess::ACCESS_WRITE),
                new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                new CanRespondToAssessment($this->getAccess())
            ),
            new Rule\All(
                new Rule\IsUser('studentId'),
                new IsParticipant(),
                new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                new IsAssessmentOpen(),
                new CanRespondToAssessment($this->getAccess())
            )
        );
    }


    /**
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException If required objects not found in route params
     * @throws NotFound If unable to find assessment context in route params
     */
    private function buildAuthRuleCanAcknowledgeSubmission()
    {
        return new Rule\All(
            new ValidAssessmentType([AssessmentType::TYPE_DUE_WORK]),
            new Rule\Any(
                new Rule\All(
                    new Rule\IsSuperuser(),
                    new ParticipantHasNoInteractions()
                ),
                new Rule\All(
                    new Rule\HasRole(Role::STAFF),
                    $this->buildAuthRuleHomepageAccess(UserAccess::ACCESS_WRITE),
                    new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                    new CanRespondToAssessment($this->getAccess()),
                    new ParticipantHasNoInteractions()
                ),
                // Participants may now acknowledge their own submissions.
                new Rule\All(
                    new Rule\IsUser('studentId'),
                    new IsParticipant(),
                    new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                    new IsAssessmentOpen(),
                    new ParticipantHasNoInteractions()
                )
            )
        );
    }


    /**
     * Prevent navigation to non-project assessment spec status pages
     *
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException If unable to locate required object in route params
     * @throws NotFound
     * @throws InvalidPath
     */
    private function buildAuthRuleCanViewStatus()
    {
        return new Rule\All(
            new AssessmentNotDeleted(),
            new Rule\Any(
                $this->buildAuthRuleHomepageAccess(UserAccess::ACCESS_WRITE),
                new Rule\HasRole(Role::STAFF))
            ,
            new IsImplementation()
        );
    }


    /**
     * @param int $accessLevel One of the UserAccess::ACCESS_* constants
     * @return Rule\HasHomepageAccess
     * @throws NotFound
     */
    private function buildAuthRuleHomepageAccess($accessLevel)
    {
        return new Rule\HasHomepageAccess (
            $accessLevel,
            function (array $params)
            {
                if (isset($params['assessment'])) {
                    $ctx = $params['assessment']->getContext()->getFolder()->getId();
                } elseif (isset($params['participant'])) {
                    $ctx = $params['participant']->getAssessable()->getContext()->getFolder()->getId();
                } elseif (isset($params['context'])) {
                    $ctx = $params['context']->getFolder()->getId();
                } else {
                    throw new NotFound('No assessment or context preloaded');
                }

                return $ctx;
            },
            $this->getAccess()
        );
    }


    /**
     * To view a summary page requires:
     * - assessment not soft-deleted
     * - current user is superuser or can see assessment, and
     * - assessment is not an assessment specification
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     * @throws InvalidPath If URL does not exist for this user
     */
    private function buildAuthRuleCanViewSummary()
    {
        return new Rule\All(
            new AssessmentNotDeleted(),
            new IsImplementation(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new CanSeeParticipation($this->getAccess(), $this->getConfig()->get('restrict_student_submissions'))
            )
        );
    }

}
