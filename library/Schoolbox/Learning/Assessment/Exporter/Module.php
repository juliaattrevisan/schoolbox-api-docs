<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LessonPlanLinkedAssessmentsMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Activities\Replicator as LessonPlanActivitiesReplicator;
use Schoolbox\Learning\Authorizer\Rule\AssessmentNotDeleted;
use Schoolbox\Learning\Authorizer\Rule\IsSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Assessment\Replicator;
use Alaress\Authorizer\Rule;

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
     * @throws \InvalidArgumentException
     * @throws ApplicationException
     */
    protected function registerRoutes(): void
    {
        // preloaders to use
        $preloadAssessment = function ($params) {
            return $this->preloadAssessment($params);
        };
        $preloadUnitSpec = function ($params) {
            return $this->preloadUnitSpec($params);
        };

        // rules in use
        $isStaff = $this->getAuthRule('isStaffOrSuper');
        $isSuperuser = $this->getAuthRule('isSuperuser');

        $canExport = new Rule\All(
            new Rule\IsLoggedIn(),
            new AssessmentNotDeleted(),
            $isStaff,
            new IsSpecification()
        );

        $canExportForUnit = new Rule\All(
            new Rule\IsLoggedIn(),
            $isStaff
        );

        // register the routes
        $route = '/assessment/{assessmentId:\d+}';
        $this->register('GET', $route, 'getExportFormForAssessment', $canExport, $preloadAssessment);
        $this->register('GET', '/assessment/csv', 'getCSV', $isSuperuser);
        $this->register('POST', $route, 'postExportForAssessment', $canExport, $preloadAssessment);

        // register the routes
        $routeForUnit = '/unit/{unitSpecId:\d+}';
        $this->register('GET', $routeForUnit, 'getExportFormForUnit', $canExportForUnit, $preloadUnitSpec);
        $this->register('GET', '/unit/csv', 'getCSVForUnit', $isSuperuser);
        $this->register('POST', $routeForUnit, 'postExportForUnit', $canExportForUnit, $preloadUnitSpec);
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws ApplicationException
     * @throws \Exception
     */
    protected function preloadAssessment(array $params): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException\Deleted $e) {
            $assessment = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        // If the assessment isn't a specification - it can't be imported anywhere
        /** @var IAssessable $assessment */
        if (!$assessment->isSpecification()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment
        ];
    }


    /**
     * Convert route params into a unitSpec object
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws ApplicationException
     * @throws \Exception
     */
    protected function preloadUnitSpec(array $params): array
    {
        try {
            $unitSpec = $this->getUnitSpecificationMapper()->findById($params['unitSpecId']);
        } catch (MapperException\Deleted $e) {
            $unitSpec = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'unitSpec' => $unitSpec
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
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Access::class),
            $this->getAssessmentMapperProxy(),
            $this->getCourseImplementationMapper(),
            $this->getUnitImplementationMapper(),
            new Replicator(
                $this->getAssessmentMapperProxy(),
                $this->getQuizMapper(),
                $this->getMapperFactory()->get(RubricDataSource::class),
                new LessonPlanActivitiesReplicator(
                    $this->getMapperFactory()->get(LessonPlanLinkedAssessmentsMapper::class),
                    $this->getAssessmentMapperProxy(),
                ),
            ),
            $this->injector->get('filters.assessmentIdFinder'),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\Project\ResultCache\Mapper::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     * @throws \InvalidArgumentException
     */
    protected function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return QuizMapper
     * @throws \InvalidArgumentException
     */
    protected function getQuizMapper(): QuizMapper
    {
        return $this->getMapperFactory()->get(QuizMapper::class);
    }


    /**
     * @return ErrorMapper
     * @throws \InvalidArgumentException
     */
    protected function getErrorMapper(): ErrorMapper
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }


    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy(): AssessmentMapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return CourseImplementationMapper
     * @throws \InvalidArgumentException
     */
    protected function getCourseImplementationMapper(): CourseImplementationMapper
    {
        return $this->getMapperFactory()->get(CourseImplementationMapper::class);
    }


    /**
     * @return UnitImplementation\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getUnitImplementationMapper(): UnitImplementation\Mapper
    {
        return $this->getMapperFactory()->get(UnitImplementation\Mapper::class);
    }


    /**
     * @return UnitSpecification\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getUnitSpecificationMapper(): UnitSpecification\Mapper
    {
        return $this->getMapperFactory()->get(UnitSpecification\Mapper::class);
    }


    /**
     * @return StudentSubmissionMapper
     * @throws \InvalidArgumentException
     */
    protected function getStudentSubmissionMapper(): StudentSubmissionMapper
    {
        return $this->getMapperFactory()->get(StudentSubmissionMapper::class);
    }


    /**
     * @return SubmissionReturnMapper
     * @throws \InvalidArgumentException
     */
    protected function getSubmissionReturnMapper(): SubmissionReturnMapper
    {
        return $this->getMapperFactory()->get(SubmissionReturnMapper::class);
    }


    /**
     * @return StorageMapper
     * @throws \InvalidArgumentException
     */
    protected function getStorageMapper(): StorageMapper
    {
        return $this->getMapperFactory()->get(StorageMapper::class);
    }


    /**
     * @return FolderMapper
     * @throws \InvalidArgumentException
     */
    protected function getFolderMapper(): FolderMapper
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
