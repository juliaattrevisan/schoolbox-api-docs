<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplMapperProxy;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecMapperProxy;
use Schoolbox\Learning\Assessment;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

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

        // register the routes
        $route = "/assessment/{assessmentId:\\d+}";
        $this->register('GET', $route, 'getExportForm', $isStaff, $preloadAssessment);
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
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
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

}
