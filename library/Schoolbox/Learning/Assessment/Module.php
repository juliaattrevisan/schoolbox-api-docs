<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use OutOfBoundsException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\File as StudentSubmission;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplMapperProxy;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecMapperProxy;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

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
        $isStaff   = $this->getAuthRule('isStaffOrSuper');
        $canSubmit = $this->getAuthRule('isStaffOrStudent');

        // pluralise for REST
        $contextTypes = AssessmentContextType::getStringValuesAsRegex(function ($name) { return $name.'s'; });

        // tokenised route chunks
        $assRoute     = '/assessments/{assessmentId:\d+}';
        $assListRoute = "/{contextType:$contextTypes}/{contextId:\\d+}/assessments";
        $studentParam = '{studentId:\d+}';


        // register the routes
        $this->register('GET',  "$assListRoute",     'getAssessmentsByContext', $isSuper, $preloadContext);
        $this->register('POST', "$assListRoute",     'postCreateAssessment',    $isStaff, $preloadContext);
        $this->register('GET',  "$assListRoute/new", 'getCreateAssessmentForm', $isStaff, $preloadContext);

        $this->register('GET',  "$assRoute",        'getAssessment',            $loggedIn, $preloadAssessment);
        $this->register('POST', "$assRoute",        'postUpdateAssessment',     $isStaff,  $preloadAssessment);
        $this->register('PATCH', "$assRoute",       'patchUpdateAssessment',    $isStaff,  $preloadAssessment);
        $this->register('GET',  "$assRoute/modify", 'getUpdateAssessmentForm',  $isStaff,  $preloadAssessment);
        $this->register('POST', "$assRoute/delete", 'postDeleteAssessment',     $isStaff,  $preloadAssessment);
        $this->register('GET',  "$assRoute/status", 'getStatus',                $isStaff,  $preloadAssessment);

        $this->register('GET',  "$assRoute/$studentParam",        'getSummary',            $canSubmit, $preloadParticipant);
        $this->register('GET',  "$assRoute/$studentParam/submit", 'getCreateResponseForm', $canSubmit, $preloadParticipant);
        $this->register('POST', "$assRoute/$studentParam/submit", 'postCreateResponse',    $canSubmit, $preloadParticipant);
    }

    /**
     * Set the name of the controller to execute, based on the given assessment
     *
     * @param IAssessable $a
     * @return string
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
            $assessment  = $this->preloadAssessment($params)['assessment'];
            $student     = $this->getUserMapper()->findById($params['studentId']);

            /* Load participant's Responses and Feedbacks
             */
            $this->getParticipantMapperProxy()->findForAssessment($assessment, $student);

            /** @var IAssessable $assessment */
            $participant = $assessment->getParticipant($student);

        } catch (OutOfBoundsException $e) {
            // given user/assessment combination is invalid: not a participant
            throw new NotFound();

        } catch (MapperException $e) {
            // either assessment or user was not found
            throw new NotFound();
        }

        return [
            'participant' => $participant
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
            $contextFactory = new Assessment\DataModel\Context\Factory($this->getFolderMapper(), $this->getUnitImplMapperProxy(), $this->getAssessmentMapperProxy(), $this->getUnitSpecMapperProxy());
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
     * @return array
     */
    protected function preloadAssessment(array $params)
    {
        try {
            $a = $this->getAssessmentMapperProxy()->findById($params['assessmentId']);
        } catch (MapperException\Deleted $e){
            $a = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        // TODO evaluate the necessity of this expensive call here
//        $this->loadParticipantsIntoAssessment($a);

        // set the controller
        $this->setControllerFromAssessment($a);

        return [
            'assessment' => $a
        ];
    }

//    /**
//     * Recursive function for loading participation
//     * TODO put this in the mapper
//     *
//     * @param IAssessable $a
//     */
//    private function loadParticipantsIntoAssessment(IAssessable $a)
//    {
//        $a->setAllParticipants($this->getParticipantMapperProxy()->findForAssessment($a));
//
//        if ($a instanceof Project) {
//            foreach ($a->getAssessables() as $b) {
//                // recurse
//                $this->loadParticipantsIntoAssessment($b);
//            }
//        }
//    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
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
        $lc['transactor']  = $injector->lazyNew(Transactor::class, [
            'folderMapper'                  => $this->getMapperFactory()->getLazy(FolderMapper::class),
            'unitImplementationMapperProxy' => $injector->lazyGet('unitImplementationDataSource'),
            'unitSpecificationMapperProxy'  => $injector->lazyGet('unitSpecificationDataSource'),
            'assessmentMapperProxy'         => $injector->lazyGet('assessmentDataSource'),
            'router'                        => $injector->lazyGet('router'),
        ]);

        // abstract controller
        $ac =& $injector->params[Controller::class];
        $ac['bakery'] = $injector->lazyNew(Breadcrumb\Factory::class, [
            'router' => $injector->lazyGet('router')
        ]);

        // due work specific
        $dc =& $injector->params[Assessment\DueWork\Controller::class];
        $dc['rubricMapper']             = $this->getMapperFactory()->getLazy(SubmissionBox\Rubric\RubricMapper::class);
        $dc['resultSetMapper']          = $this->getMapperFactory()->getLazy(SubmissionBox\Rubric\ResultMapper::class);
        $dc['workTypeMapper']           = $this->getMapperFactory()->getLazy(SubmissionBox\WorkTypeMapper::class);
        // TODO get this out of DI
        $dc['dueWorkResponseMapper']    = $injector->lazyNew(
            DueWorkResponseMapper::class,
            ['mapper' => $this->getMapperFactory()->getLazy(StudentSubmissionMapper::class)]
        );

        // project specific
        // $pc =& $injector->params[Assessment\Project\Controller::class];

        // quiz specific
        $qc =& $injector->params[Assessment\Quiz\Controller::class];
        $qc['participantMapper'] = $injector->lazyGet('participantDataSource');
        $qc['quizMapper']        = $this->getMapperFactory()->getLazy(QuizMapper::class);
        $qc['quizSectionMapper'] = $this->getMapperFactory()->getLazy(QuizSectionMapper::class);
        $qc['quizAttemptMapper'] = $this->getMapperFactory()->getLazy(QuizAttemptMapper::class);

        // task specific
        // $tc =& $injector->params[Assessment\Task\Controller::class];

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
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return UnitImplMapperProxy
     */
    protected function getUnitImplMapperProxy()
    {
        return $this->injector->get('unitImplementationDataSource');
    }

    /**
     * @return UnitSpecMapperProxy
     */
    protected function getUnitSpecMapperProxy()
    {
        return $this->injector->get('unitSpecificationDataSource');
    }

    /**
     * @return StudentSubmissionMapper
     */
    protected function getStudentSubmissionMapper()
    {
        return $this->getMapperFactory()->get(StudentSubmissionMapper::class);
    }

    /**
     * @return SubmissionReturnMapper
     */
    protected function getSubmissionReturnMapper()
    {
        return $this->getMapperFactory()->get(SubmissionReturnMapper::class);
    }

    /**
     * @return StorageMapper
     */
    protected function getStorageMapper()
    {
        return $this->getMapperFactory()->get(StorageMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return Participant\DataSource
     */
    protected function getParticipantMapperProxy()
    {
        return $this->injector->get('participantDataSource');
    }

}
