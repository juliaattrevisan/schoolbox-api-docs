<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
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
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

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
     */
    protected function registerRoutes()
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
     */
    protected function preloadAssessment(array $params)
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException\Deleted $e){
            $assessment = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        // If the assessment isn't a specification - it can't be imported anywhere
        if (!$assessment->getContext() instanceof UnitSpecification) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessmentSpec' => $assessment
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
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'access'                   => Access::getInstance(),
                    'assessmentDataSource'     => $this->getAssessmentMapperProxy(),
                    'unitImplementationMapper' => $this->getUnitImplementationMapper(),
                    'replicator'               => $injector->lazyNew(Replicator::class, [
                        'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                        'quizMapper'            => $this->getMapperFactory()->getLazy(QuizMapper::class),
                    ]),
                    'assessmentIdFinder'       => $injector->lazy(function () {
                        /** @var Filter\Mapper $filterMapper */
                        $filterMapper = $this->getMapperFactory()->get(Filter\Mapper::class);
                        return Filter\Filters\Assessment\IdFinder::getInstance(
                            $filterMapper,
                            $this->getMapperFactory()->get(Assessment\Mapper::class)
                        );
                    }),
                ]
            )
        );
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
     * @return QuizMapper
     */
    protected function getQuizMapper()
    {
        /** @var QuizMapper $quizMapper */
        $quizMapper = $this->getMapperFactory()->get(QuizMapper::class);
        return $quizMapper;
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

}
