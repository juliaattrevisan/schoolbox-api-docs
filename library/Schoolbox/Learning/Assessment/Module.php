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
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Authorizer\Rule\CanSeeAssessment;
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
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseDataSource;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use UnexpectedValueException;

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
     */
    protected function registerRoutes()
    {
        // preloaders to use
        $preloadContext     = function ($params) { return $this->preloadContext($params); };
        $preloadAssessment  = function ($params) { return $this->preloadAssessment($params); };
        $preloadParticipant = function ($params) { return $this->preloadParticipant($params); };

        // rules in use
        $isSuper   = $this->getAuthRule('isSuperuser');
        $loggedIn  = $this->getAuthRule('isLoggedIn');
        $canWrite  = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            function (array $params)
            {
                if (isset($params['assessment'])) {
                    $ctx = $params['assessment']->getContext()->getFolder()->getId();
                } elseif (isset($params['context'])) {
                    $ctx = $params['context']->getFolder()->getId();
                } else {
                    throw new NotFound("No assessment or context preloaded");
                }

                return $ctx;
            },
            $this->getAccess()
        );

        $canRead = new Rule\HasHomepageAccess (
                UserAccess::ACCESS_READ,
                function (array $params)
                {
                    if (isset($params['assessment'])) {
                        $ctx = $params['assessment']->getContext()->getFolder()->getId();
                    } elseif (isset($params['context'])) {
                        $ctx = $params['context']->getFolder()->getId();
                    } else {
                        throw new NotFound("No assessment or context preloaded");
                    }

                    return $ctx;
                },
                $this->getAccess()
        );

        $participantView = new Rule\All(
            new Rule\Any(
                new Rule\IsSuperuser(),
                new CanSeeAssessment($this->getAccess(), $this->getConfig())
            ),
            new Rule\Callback(function(array $params){
                if (!isset($params['assessment']) && !isset($params['participant'])) {
                    throw new NotFound("No assessment or participant preloaded");
                }
                /** @var IAssessable $assessment */
                $assessment = $params['assessment'] ?: $params['participant']->getAssessable() ;
                if($assessment->getBaseContext() instanceof UnitSpecification){
                    throw new InvalidPath("Cannot view a summary page for an Assessment Specification");
                }
            })
        );

        $canSubmit = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasRole(Role::STAFF),
            new Rule\IsUser('studentId')
        );

        // Prevent navigation to non-project assessment spec status pages
        $canWriteAndNotSpec = new Rule\All(
            $canWrite,
            new Rule\Callback(function(array $params){
                if (!isset($params['assessment'])) {
                    throw new NotFound("No assessment preloaded");
                }
                /** @var IAssessable $assessment */
                $assessment = $params['assessment'];
                if(!$assessment instanceof Project && $assessment->getBaseContext() instanceof UnitSpecification){
                    throw new InvalidPath("Cannot view a status page for an Assessment Specification");
                }
            })
        );


        // pluralise for REST
        $contextTypes = AssessmentContextType::getStringValuesAsRegex(function ($name) { return $name.'s'; });

        // tokenised route chunks
        $assRoute     = '/assessments/{assessmentId:\d+}';
        $assListRoute = "/{contextType:$contextTypes}/{contextId:\\d+}/assessments";
        $studentParam = '{studentId:\d+}';


        // register the routes
        $this->register('GET',      "$assListRoute",                    'getAssessmentsByContext',  $isSuper,               $preloadContext);
        $this->register('POST',     "$assListRoute",                    'postCreateAssessment',     $canWrite,              $preloadContext); // a || c
        $this->register('POST',     "$assListRoute/reorder",            'postReorderAssessments',   $canWrite,              $preloadContext); // a || c
        $this->register('GET',      "$assListRoute/new",                'getCreateAssessmentForm',  $canWrite,              $preloadContext); // a || c

        $this->register('GET',      "$assRoute",                        'getAssessment',            $loggedIn,              $preloadAssessment);
        $this->register('POST',     "$assRoute",                        'postUpdateAssessment',     $canWrite,              $preloadAssessment); // a || c
        $this->register('PATCH',    "$assRoute",                        'patchUpdateAssessment',    $canWrite,              $preloadAssessment); // a || c
        $this->register('GET',      "$assRoute/modify",                 'getUpdateAssessmentForm',  $canWrite,              $preloadAssessment); // a || c
        $this->register('POST',     "$assRoute/delete",                 'postDeleteAssessment',     $canWrite,              $preloadAssessment); // a || c
        $this->register('GET',      "$assRoute/status",                 'getStatus',                $canWriteAndNotSpec,    $preloadAssessment); // a || c
        $this->register('GET',      "$assRoute/overview",               'getOverview',              $canWrite,              $preloadAssessment); // a || c
        $this->register('GET',      "$assRoute/overview/export",        'downloadOverview',         $canWrite,              $preloadAssessment); // a || c
        $this->register('GET',      "$assRoute/submissions/download",   'downloadSubmissions',      $canWrite,              $preloadAssessment); // a || c

        $this->register('GET',      "$assRoute/$studentParam",          'getSummary',               $participantView,       $preloadParticipant); // s || p
        $this->register('GET',      "$assRoute/preview",                'getPreview',               $canRead,               $preloadAssessment); // s || p
        $this->register('GET',      "$assRoute/$studentParam/submit",   'getCreateResponseForm',    $canSubmit,             $preloadParticipant); // p
        $this->register('POST',     "$assRoute/$studentParam/submit",   'postCreateResponse',       $canSubmit,             $preloadParticipant); // p
    }

    /**
     * Set the name of the controller to execute, based on the given assessment
     *
     * @param IAssessable $a
     * @return void
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
     * @throws NotFound
     */
    protected function preloadParticipant(array $params)
    {
        try {

            // obtain the assessment from our other preload
            $student     = $this->getUserMapper()->findById($params['studentId']);
            $assessment  = $this->preloadAssessment($params, $student)['assessment'];

            $access = $this->getAccess()->getFolderAccess($student, [$assessment->getContext()->getFolder()->getId()]);

            /* Allow the currently logged in user to accesss the student's Assessment Summary
             *
             * This allows write-accessors to view a typically read-access page ie. when a teacher
             * wants to preview a task's summary screen (see SA-2193)
             *
             * This pretends the teacher is a participant
             */
            if ($access >= UserAccess::ACCESS_WRITE) {
                $participant = new Participant($student, $assessment);
            }
            /* Allow read-accessors to see Summary
             */
            elseif ($access == UserAccess::ACCESS_READ) {
                /** @var IAssessable $assessment */
                $participant = $assessment->getParticipant($student);
            }
            /* Nobody else allowed
             */
            else {
                throw new Forbidden();
            }
        } catch (UnexpectedValueException $e) {

            // Student isn't a participant
            throw new Forbidden();

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
     * @param array $params
     * @param User  $student If provided, only load this student's participation
     * @return array
     */
    protected function preloadAssessment(array $params, User $student = null)
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e){
            $assessment = $e->getDeletedObject();
        } catch (MapperException $e) {
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
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
        $this->addTemplatePath($templateLoader, __DIR__ . '/../MarkScheme/templates', 'learningMarkScheme');
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
        $lc['transactor']  = $injector->get('transactor');

        // abstract controller
        $ac =& $injector->params[Controller::class];
        $ac['bakery'] = $injector->lazyNew(Breadcrumb\Factory::class, [
            'router' => $injector->lazyGet('router')
        ]);

        // due work specific
        $dc =& $injector->params[Assessment\DueWork\Controller::class];
        $dc['rubricMapper']             = $this->getMapperFactory()->getLazy(SubmissionBox\Rubric\RubricMapper::class);
        $dc['resultSetMapper']          = $this->getMapperFactory()->getLazy(SubmissionBox\Rubric\ResultMapper::class);
        $dc['submissionTypeMapper']     = $this->getMapperFactory()->getLazy(SubmissionType\Mapper::class);
        // TODO get this out of DI
        $dc['dueWorkResponseMapper']    = $injector->lazyNew(
            DueWorkResponseMapper::class,
            ['mapper' => $this->getMapperFactory()->getLazy(StudentSubmissionMapper::class)]
        );
        $dc['participantMapper'] = $injector->lazyNew(ParticipantDataSource::class);

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

}
