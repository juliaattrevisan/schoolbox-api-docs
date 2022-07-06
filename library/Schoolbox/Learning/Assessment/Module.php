<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use DomainException;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Course\Task\CompletionMapper as TaskResponseMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\DataModel\Result\Mapper as RubricResultSetMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseDataSource;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Authorizer\Rule\AssessmentNotDeleted;
use Schoolbox\Learning\Authorizer\Rule\CanAcknowledgeAssessment;
use Schoolbox\Learning\Authorizer\Rule\CanPreview;
use Schoolbox\Learning\Authorizer\Rule\CanRespondToAssessment;
use Schoolbox\Learning\Authorizer\Rule\CanSeeParticipation;
use Schoolbox\Learning\Authorizer\Rule\CanViewAssessable;
use Schoolbox\Learning\Assessment\DueWork\Authorizer\Rule\CanSelfAssess;
use Schoolbox\Learning\Authorizer\Rule\IsAssessmentOpen;
use Schoolbox\Learning\Authorizer\Rule\IsDeletionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsImplementation;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsModifyUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsSafeExamBrowserRequired;
use Schoolbox\Learning\Authorizer\Rule\IsSimilarityCheckEnabled;
use Schoolbox\Learning\Authorizer\Rule\ParticipantHasNoInteractions;
use Schoolbox\Learning\Authorizer\Rule\ValidAssessmentType;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiAssessmentScoreMapper;
use Schoolbox\LTI\Provider\Mapper as LtiToolMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Module
 *
 * @package Schoolbox\Learning\Assessment
 */
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
     * @throws InvalidArgumentException
     */
    protected function registerRoutes()
    {
        // preloaders to use
        $preloadContext                = function ($params) {
            return $this->preloadContext($params);
        };
        $preloadAssessment             = function ($params) {
            return $this->preloadAssessment($params);
        };
        $preloadParticipant            = function ($params) {
            return $this->preloadAssessmentAndStudent($params);
        };

        // rules in use
        $isSuper                  = $this->getAuthRule('isSuperuser');
        $loggedIn                 = $this->getAuthRule('isLoggedIn');
        $canWrite                 = $this->buildAuthRuleHasInstructorAccess();
        $canViewSummary           = $this->buildAuthRuleCanViewSummary();
        $canSubmit                = $this->buildAuthRuleCanSubmit();
        $canViewStatus            = $this->buildAuthRuleCanViewStatus();
        $canViewSimilarityReport  = $this->buildAuthRuleCanViewSimilarityReport();
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
                new AssessmentNotDeleted(),
                new IsModifyUnLocked(new LockedChecker($this->getTermMapper()))
            )
        );

        $mustUseSeb = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STUDENT),
            new Rule\IsConfigOn(ConfigMapper::NAME_LMS_SEB_ENABLED, $this->getConfig()),
            new IsSafeExamBrowserRequired(),
        );

        // pluralise for REST
        $contextTypes = AssessmentContextType::getStringValuesAsRegex(function ($name) {
            return $name . 's';
        });

        // tokenised route chunks
        $assRoute      = '/assessments/{assessmentId:\d+}';
        $assWeight     = '/assessments/weight/{assessmentId:\d+}';
        $assVisibility = '/assessments/visibility/{assessmentId:\d+}';
        $assListRoute  = "/{contextType:$contextTypes}/{contextId:\\d+}/assessments";
        $studentParam  = '{studentId:\d+}';

        // register the routes

        $this->register('GET', "$assRoute/seb", 'getSebDownload', $mustUseSeb, $preloadAssessment);
        $this->register('GET', "$assRoute/sebConfig", 'getSebConfig', $mustUseSeb, $preloadAssessment);
        $this->register('GET', "$assListRoute", 'getAssessmentsByContext', $isSuper, $preloadContext);
        $this->register('POST', "$assListRoute", 'postCreateAssessment', $canWrite, $preloadContext); // a || c
        $this->register('POST', "$assListRoute/reorder", 'postReorderAssessments', $canWrite, $preloadContext); // a || c
        $this->register('GET', "$assListRoute/new", 'getCreateAssessmentForm', $canWrite, $preloadContext); // a || c

        $this->register('GET', "$assRoute", 'getAssessment', $loggedIn, $preloadAssessment);
        $this->register('POST', "$assRoute", 'postUpdateAssessment', $canWrite, $preloadAssessment); // a || c
        $this->register('POST', "$assWeight", 'updateWeight', $canModify, $preloadAssessment); // a || c
        $this->register('POST', "$assVisibility", 'updateVisibility', $canModify, $preloadAssessment); // a || c
        $this->register('PATCH', "$assRoute", 'patchUpdateAssessment', $canWrite, $preloadAssessment); // a || c
        $this->register('GET', "$assRoute/modify", 'getUpdateAssessmentForm', $canModify, $preloadAssessment); // a || c
        $this->register('POST', "$assRoute/delete", 'postDeleteAssessment', $canDelete, $preloadAssessment); // a || c
        $this->register('GET', "$assRoute/status", 'getStatus', $canViewStatus, $preloadAssessment); // a || c
        $this->register('GET', "$assRoute/overview", 'getOverview', $canViewStatus, $preloadAssessment); // a || c
        $this->register('GET', "$assRoute/overview/export", 'downloadOverview', $canViewStatus, $preloadAssessment); // a || c
        $this->register('GET', "$assRoute/submissions/download", 'downloadSubmissions', $canViewStatus, $preloadAssessment); // a || c

        $this->register('GET', "$assRoute/$studentParam", 'getSummary', $canViewSummary, $preloadParticipant); // s || p
        $this->register('GET', "$assRoute/$studentParam/seb", 'getSummaryViaSeb', null, $preloadParticipant); // s || p
        $this->register('GET', "$assRoute/$studentParam/submit", 'getCreateResponseForm', $canSubmit, $preloadParticipant); // p
        $this->register('POST', "$assRoute/$studentParam/submit", 'postCreateResponse', $canSubmit, $preloadParticipant); // p
        $this->register('POST', "$assRoute/$studentParam/received", 'postStatusAcknowledge', $canAckSubmission, $preloadParticipant); // p
        $this->register('POST', "$assRoute/$studentParam/ack", 'postPendingAcknowledge', $canAckSubmission, $preloadParticipant); // p
        $this->register('POST', "$assRoute/$studentParam/ackSelf", 'postSelfAcknowledge', $canAckSubmission, $preloadParticipant); // p

        $this->register('POST', "$assRoute/locations", 'getLocationForm', $canModify, $preloadAssessment);

        /* Similarity Report report
         */
        $this->register('GET', "{$assRoute}/status/similarity", 'getSimilarityReport', $canViewSimilarityReport, $preloadAssessment);
        $this->register('GET', "{$assRoute}/status/similarity/payload", 'getSimilarityReportPayload', $canViewSimilarityReport, $preloadAssessment);


        // LTI assessment related routes
        // TODO extract to a different module:
        //      they are only here so that they can use the standard assessment preloads and permissions

        // LTI assessment launch intermediaries, depending on the tool target:
        // * iframe: open launch in an iframe
        // * window: redirect to launch
        $this->register(
            self::METHOD_GET,
            "$assRoute/lti/status",
            'getLtiStatusLaunch',
            $canViewStatus,
            $preloadAssessment,
        );
        $this->register(
            self::METHOD_GET,
            "$assRoute/$studentParam/lti/submit",
            'getLtiCreateResponseLaunch',
            $canSubmit,
            $preloadParticipant,
        );

        // LTI assessment launch: launch the LTI tool itself
        $this->register(
            self::METHOD_GET,
            "$assRoute/lti/launch/instructor",
            'getLaunchAssessmentAsInstructor',
            $canViewStatus,
            $preloadAssessment,
        );
        $this->register(
            self::METHOD_GET,
            "$assRoute/lti/launch/student/$studentParam",
            'getLaunchAssessmentAsStudent',
            $canSubmit,
            $preloadParticipant,
        );
    }


    /**
     * Set the name of the controller to execute, based on the given assessment
     *
     * @param IAssessable $a
     * @return void
     * @throws UnexpectedValueException If unable to recognise IAssessable object
     */
    public function setControllerFromAssessment(IAssessable $a): void
    {
        // TODO centralise this into a factory
        $map = [
            AssessmentType::TYPE_DUE_WORK       => Assessment\DueWork\Controller::class,
            AssessmentType::TYPE_PROJECT        => Assessment\Project\Controller::class,
            AssessmentType::TYPE_QUIZ           => Assessment\Quiz\Controller::class,
            AssessmentType::TYPE_TASK           => Assessment\Task\Controller::class,
            AssessmentType::TYPE_LTI_ASSESSMENT => Assessment\Lti\Controller::class,
        ];

        // set the controller class for later
        $this->controllerClass = $map[AssessmentType::objectToString($a)];
    }


    /**
     * Convert route params into a participant object
     *
     * @param array $params
     * @return array
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws NotFound
     */
    protected function preloadAssessmentAndStudent(array $params): array
    {
        try {
            // obtain the assessment from our other preload
            $student     = $this->getUserMapper()->findById($params['studentId']);
            $assessment  = $this->preloadAssessment($params, $student)['assessment'];
        } catch (UnexpectedValueException $e) {
            // Student isn't a participant
            throw new NotFound();
        } catch (OutOfBoundsException $e) {
            // given user/assessment combination is invalid: not a participant
            throw new NotFound();
        } catch (MapperException $e) {
            // either assessment or user was not found
            throw new NotFound();
        }

        return [
            'assessment'  => $assessment,
            'student'     => $student, //IsParentOfStudent accesses this
        ];
    }

    /**
     * Convert route params into a context object
     *
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function preloadContext(array $params): array
    {
        try {
            $contextFactory = new Assessment\DataModel\Context\Factory(
                $this->getFolderMapper(),
                $this->getUnitImplementationMapper(),
                $this->getAssessmentMapperProxy(),
                $this->getUnitSpecificationMapper()
            );
            $c = $contextFactory->produce($params['contextType'], $params['contextId']);
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
     * @param null|User $student If provided, only load this student's participation
     * @return array
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    protected function preloadAssessment(array $params, User $student = null): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e) {
            $assessment = $e->getDeletedObject();
        } catch (MapperException $e) {
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
     * Build a controller for this class name.
     *
     * @template T of Base
     * @param class-string<T> $className
     * @return Base
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function buildControllerForClass(string $className): Base
    {
        // Common dependencies across the controllers this module _may_ create.
        $userMapper       = $this->getMapperFactory()->get(UserMapper::class);
        $errorMapper      = $this->getMapperFactory()->get(ErrorMapper::class);
        $workTypeMapper   = $this->getMapperFactory()->get(WorkTypeMapper::class);
        $courseSpecMapper = $this->getMapperFactory()->get(CourseSpecificationMapper::class);
        $unitSpecMapper   = $this->getMapperFactory()->get(UnitSpecificationMapper::class);
        $folderMapper     = $this->getMapperFactory()->get(FolderMapper::class);
        $termMapper       = $this->getMapperFactory()->get(Term\Mapper::class);
        $viewLatestMapper = $this->getMapperFactory()->get(LatestViewMapper::class);

        /** @var AssessmentMapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->injector->get('assessmentDataSource');

        /** @var Transactor $transactor */
        $transactor = $this->injector->get('transactor');

        /** @var ParticipantDataSource $participantDataSource */
        $participantDataSource = $this->injector->get('participantDataSource');

        $bakery = new Breadcrumb\Factory(
            $this->getRouter(),
            $this->getAuthorizer(),
            $this->getAccess(),
        );

        $replicator = new Replicator(
            $assessmentDataSource,
            $this->getMapperFactory()->get(QuizMapper::class),
            $this->getMapperFactory()->get(RubricDataSource::class),
        );

        switch ($className) {
            case ListController::class:
                return new ListController(
                    $userMapper,
                    $errorMapper,
                    $assessmentDataSource,
                    $this->getAccess(),
                    $transactor,
                    $participantDataSource,
                    $this->getUnitSpecificationMapper(),
                );

            case Assessment\DueWork\Controller::class:
                return new Assessment\DueWork\Controller(
                    $userMapper,
                    $errorMapper,
                    $assessmentDataSource,
                    $workTypeMapper,
                    $this->getAccess(),
                    $transactor,
                    $bakery,
                    $this->getMapperFactory()->get(RubricDataSource::class),
                    $this->getMapperFactory()->get(RubricResultSetMapper::class),
                    $this->injector->get('submissionDataSource'),
                    $participantDataSource,
                    $termMapper,
                    $this->getMapperFactory()->get(SingleFile\Mapper::class),
                    $this->injector->get('kamiConnector'),
                    $this->getMapperFactory()->get(Kami\Mapper::class),
                    $participantDataSource,
                    $courseSpecMapper,
                    $unitSpecMapper,
                    $replicator,
                    $this->injector->get('pdfGenerator'),
                    $this->getMapperFactory()->get(\Schoolbox\Learning\Turnitin\File\Mapper::class),
                    $this->injector->get('feedbackDataSource'),
                    $folderMapper,
                    $viewLatestMapper,
                    $this->injector->get('filesystem.storageProxy'),
                );

            case Assessment\Project\Controller::class:
                return new Assessment\Project\Controller(
                    $userMapper,
                    $errorMapper,
                    $assessmentDataSource,
                    $workTypeMapper,
                    $this->getAccess(),
                    $transactor,
                    $bakery,
                    $termMapper,
                    $participantDataSource,
                    $courseSpecMapper,
                    $unitSpecMapper,
                    $replicator,
                    $viewLatestMapper,
                );

            case Assessment\Quiz\Controller::class:
                return new Assessment\Quiz\Controller(
                    $userMapper,
                    $errorMapper,
                    $assessmentDataSource,
                    $workTypeMapper,
                    $this->getAccess(),
                    $transactor,
                    $bakery,
                    $this->getMapperFactory()->get(QuizMapper::class),
                    $this->getMapperFactory()->get(QuizSectionMapper::class),
                    $this->getMapperFactory()->get(QuizAttemptMapper::class),
                    $termMapper,
                    $participantDataSource,
                    $courseSpecMapper,
                    $unitSpecMapper,
                    $replicator,
                    $folderMapper,
                    $viewLatestMapper,
                );

            case Assessment\Task\Controller::class:
                return new Assessment\Task\Controller(
                    $userMapper,
                    $errorMapper,
                    $assessmentDataSource,
                    $workTypeMapper,
                    $this->getAccess(),
                    $transactor,
                    $bakery,
                    new TaskResponseDataSource($this->getMapperFactory()->get(TaskResponseMapper::class)),
                    $termMapper,
                    $participantDataSource,
                    $courseSpecMapper,
                    $unitSpecMapper,
                    $replicator,
                    $viewLatestMapper,
                );

            case Assessment\Lti\Controller::class:
                return new Assessment\Lti\Controller(
                    $userMapper,
                    $errorMapper,
                    $assessmentDataSource,
                    $workTypeMapper,
                    $this->getAccess(),
                    $transactor,
                    $bakery,
                    $termMapper,
                    $participantDataSource,
                    $courseSpecMapper,
                    $unitSpecMapper,
                    $replicator,
                    $this->getMapperFactory()->get(LtiToolMapper::class),
                    $this->getMapperFactory()->get(LtiAssessmentMapper::class),
                    $this->getMapperFactory()->get(LtiAssessmentScoreMapper::class),
                    $this->injector->get('lti.registrations'),
                    $this->injector->get('instance'),
                    $this->injector->get('lti.userCodec'),
                    $viewLatestMapper,
                );

            default:
                throw new InvalidArgumentException(
                    sprintf('Assessment controller for class "%s" not found', $className),
                );
        }
    }

    /**
     * Build a controller for one of the assessment sub-types.
     *
     * @param string $type One of the One of the `AssessmentType::TYPE_*` constants
     * @return Controller
     * @throws InvalidArgumentException    if the controller isn't a Controller
     *                                     instance
     * @throws ContainerExceptionInterface something went wrong when building
     *                                     the controller
     * @throws NotFoundExceptionInterface  something went wrong when building
     *                                     the controller
     */
    public function buildControllerForType(string $type): Controller
    {
        switch ($type) {
            case AssessmentType::TYPE_DUE_WORK:
                $controller = $this->buildControllerForClass(Assessment\DueWork\Controller::class);
                break;

            case AssessmentType::TYPE_QUIZ:
                $controller = $this->buildControllerForClass(Assessment\Quiz\Controller::class);
                break;

            case AssessmentType::TYPE_TASK:
                $controller = $this->buildControllerForClass(Assessment\Task\Controller::class);
                break;

            case AssessmentType::TYPE_PROJECT:
                $controller = $this->buildControllerForClass(Assessment\Project\Controller::class);
                break;

            case AssessmentType::TYPE_LTI_ASSESSMENT:
                $controller = $this->buildControllerForClass(Assessment\Lti\Controller::class);
                break;

            default:
                throw new InvalidArgumentException(
                    sprintf('Assessment controller for type "%s" not found', $type),
                );
        }
        if ($controller instanceof Controller) {
            return $controller;
        }
        throw new InvalidArgumentException(
            sprintf('Assessment controller for type "%s" not a "%s" instance', $type, Controller::class),
        );
    }

    /**
     * @return Base
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function buildController(): Base
    {
        return $this->buildControllerForClass($this->controllerClass);
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return object|AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return Mapper|UnitImplementationMapper
     * @throws InvalidArgumentException
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(UnitImplementationMapper::class);
    }

    /**
     * @return Mapper|UnitSpecificationMapper
     * @throws InvalidArgumentException
     */
    protected function getUnitSpecificationMapper()
    {
        return $this->getMapperFactory()->get(UnitSpecificationMapper::class);
    }

    /**
     * @return Mapper|StudentSubmissionMapper
     * @throws InvalidArgumentException
     */
    protected function getStudentSubmissionMapper()
    {
        return $this->getMapperFactory()->get(StudentSubmissionMapper::class);
    }

    /**
     * @return Mapper|SubmissionReturnMapper
     * @throws InvalidArgumentException
     */
    protected function getSubmissionReturnMapper()
    {
        return $this->getMapperFactory()->get(SubmissionReturnMapper::class);
    }

    /**
     * @return Mapper|StorageMapper
     * @throws InvalidArgumentException
     */
    protected function getStorageMapper()
    {
        return $this->getMapperFactory()->get(StorageMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     * @throws InvalidArgumentException
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }


    /**
     * @return Access
     * @throws InvalidArgumentException
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
     * - Students cannot submit if assessment does not accept online submissions AND self-reflect is off
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If required object not found in route params
     */
    private function buildAuthRuleCanSubmit()
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\All(
                    new Rule\Any(
                    // (Superuser + impl) or (instructor + !interaction-locked)
                        new Rule\All(
                            new Rule\IsSuperuser(),
                            new IsImplementation()
                        ),
                        new Rule\All(
                            $this->buildAuthRuleHasInstructorAccess(),
                            new IsInteractionUnLocked(new LockedChecker($this->getTermMapper()))
                        )
                    ),
                    new Rule\Any(
                        new CanRespondToAssessment($this->getAccess()), // SOBO
                        new CanPreview($this->getAccess())              // Preview
                    )
                ),
                new Rule\All(
                    new CanViewAssessable(new Visibility($this->getAccess())),
                    new Rule\IsUser('studentId'),
                    new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                    new IsAssessmentOpen(),
                    new Rule\Any(
                        new CanRespondToAssessment($this->getAccess()),
                        new CanSelfAssess()
                    )
                )
            )
        );
    }


    /**
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If required objects not found in route params
     */
    private function buildAuthRuleCanAcknowledgeSubmission()
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new ValidAssessmentType([AssessmentType::TYPE_DUE_WORK]),
            new Rule\Any(
                new Rule\All(
                    new Rule\IsSuperuser(),
                    new ParticipantHasNoInteractions(),
                    new CanRespondToAssessment($this->getAccess())
                ),
                new Rule\All(
                    $this->buildAuthRuleHasInstructorAccess(),
                    new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                    new CanRespondToAssessment($this->getAccess()),
                    new ParticipantHasNoInteractions()
                ),
                // Participants may now acknowledge their own submissions.
                new Rule\All(
                    new Rule\IsUser('studentId'),
                    new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                    new IsAssessmentOpen(),
                    new CanAcknowledgeAssessment($this->getAccess())
                )
            )
        );
    }


    /**
     * Prevent navigation to non-project assessment spec status pages
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If unable to locate required object in route params
     */
    private function buildAuthRuleCanViewStatus()
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new AssessmentNotDeleted(),
            $this->buildAuthRuleHasInstructorAccess(),
            new Rule\Any(
                new IsImplementation(),
                new ValidAssessmentType([AssessmentType::TYPE_PROJECT])
            )
        );
    }


    /**
     * Prevent navigation to non-project assessment spec status pages
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If unable to locate required object in route params
     */
    private function buildAuthRuleCanViewSimilarityReport(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            $this->buildAuthRuleCanViewStatus(),
            new IsSimilarityCheckEnabled()
        );
    }


    /**
     * Prevent non-instructors from marking or modifying assessments.
     *
     * This uses the `IsInstructor` validator, which is the new standard for
     * checking if a user has instructor permission over a folder.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleHasInstructorAccess(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(
                    $this->getAccess(),
                    static function (array $params): FolderModel {
                        if (isset($params['assessment'])) {
                            $ctx = $params['assessment']->getContext()->getFolder();
                        } elseif (isset($params['participant'])) {
                            $ctx = $params['participant']->getAssessable()->getContext()->getFolder();
                        } elseif (isset($params['context'])) {
                            $ctx = $params['context']->getFolder();
                        } else {
                            throw new NotFound('No assessment or context preloaded');
                        }

                        return $ctx;
                    }
                )
            )
        );
    }

    // will throw invalid arg if route params incorrect
    /**
     * To view a summary page requires:
     * - assessment not soft-deleted
     * - current user is staff|instructor or can see participation,
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If required route param not found
     */
    private function buildAuthRuleCanViewSummary(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new AssessmentNotDeleted(),
            new CanViewAssessable(new Visibility($this->getAccess())),
            new Rule\Any(
                // Instructor/staff viewing assessment details, on their own summary page
                new Rule\All(
                    $this->buildAuthRuleHasInstructorAccess(),
                    new Rule\IsUser('studentId')
                ),
                //check second, if the viewing user is admin, and the student is excluded, both this and the above rule fail.
                //but we want to see the nice response from this one "User {studentID} is excluded from this assessment"
                // Normal participants (whether explicit or dynamic participants)
                new CanSeeParticipation($this->getAccess(), $this->getConfig()->get('restrict_student_submissions'))
            )
        );
    }
}
