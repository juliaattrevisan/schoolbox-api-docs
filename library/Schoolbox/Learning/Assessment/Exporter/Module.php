<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ContainerLocked;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Di\Exception\ServiceNotObject;
use Exception;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Assessment\Replicator;

/**
 * Module for assessment export options.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessmentExporter';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ApplicationException
     */
    protected function registerRoutes(): void
    {
        // preloaders to use
        $preloadAssessment = function ($params) { return $this->preloadAssessment($params); };

        // rules in use
        $isStaff = $this->getAuthRule('isStaffOrSuper');
        $isSuperuser = $this->getAuthRule('isSuperuser');

        // register the routes
        $route = '/assessment/{assessmentId:\d+}';
        $this->register('GET', $route, 'getExportForm', $isStaff, $preloadAssessment);
        $this->register('GET', '/assessment/csv', 'getCSV', $isSuperuser);
        $this->register('POST', $route, 'postExport', $isStaff, $preloadAssessment);
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array $params
     * @return array
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     * @throws ApplicationException
     * @throws Exception
     */
    protected function preloadAssessment(array $params): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException\Deleted $e){
            $assessment = $e->getDeletedObject();
        }

        // If the assessment isn't a specification - it can't be imported anywhere
        if (!$assessment->getContext() instanceof UnitSpecification\Model) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessmentSpec' => $assessment
        ];
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     * @param ContainerInterface $injector
     * @throws ServiceNotFound
     * @throws ContainerLocked
     * @throws ServiceNotObject
     */
    public function initControllers(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'access'                    => Access::getInstance(),
                    'assessmentDataSource'      => $this->getAssessmentMapperProxy(),
                    'unitImplementationMapper'  => $this->getUnitImplementationMapper(),
                    'replicator'                => $injector->lazyNew(Replicator::class, [
                        'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                        'quizMapper'            => $this->getMapperFactory()->getLazy(QuizMapper::class),
                    ]),
                    'assessmentIdFinder'        => $injector->lazyGet('filters.assessmentIdFinder'),
                    'folderMapper'              => $this->getMapperFactory()->getLazy(FolderMapper::class),
                ]
            )
        );
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
     * @return QuizMapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getQuizMapper()
    {
        return $this->getMapperFactory()->get(QuizMapper::class);
    }


    /**
     * @return ErrorMapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }


    /**
     * @return object|AssessmentMapperProxy
     * @throws ServiceNotFound
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return UnitImplementation\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(UnitImplementation\Mapper::class);
    }


    /**
     * @return UnitSpecification\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getUnitSpecificationMapper()
    {
        return $this->getMapperFactory()->get(UnitSpecification\Mapper::class);
    }


    /**
     * @return StudentSubmissionMapper|Mapper
     * @throws InvalidArgumentException
     */
    protected function getStudentSubmissionMapper()
    {
        return $this->getMapperFactory()->get(StudentSubmissionMapper::class);
    }


    /**
     * @return SubmissionReturnMapper|Mapper
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

}
