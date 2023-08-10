<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'lessonPlanActivities';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /**
         * Same users who can access /course can edit this
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );

        $preloadCourseSpec = function (array $params) {
            try {
                return ['courseSpec' => $this->getCourseSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['courseSpec' => $e->getDeletedObject()];
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        };

        $preloadFolder = function (array $params) {
            try {
                return ['folder' => $this->getFolderMapper()->findById($params['folderId'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound();
            }
        };

        $preloadLessonPlan = function ($params) {
            return $this->preloadLessonPlan($params);
        };

        // Get all activities contained within a course
        $this->register('GET', '/course/{id}', 'getCourse', $perms, $preloadCourseSpec);
        // routes for lesson plan specifically
        $this->register('GET', '/{lessonPlanId}', 'getLink', $perms, $preloadLessonPlan);
        $this->register('POST', '/{lessonPlanId}', 'createLink', $perms, $preloadLessonPlan);
        $this->register('DELETE', '/{lessonPlanId}/{activity}', 'deleteLink', $perms, $preloadLessonPlan);
        // get lessonplans for homepage
        $this->register('GET', '/class/{folderId}', 'getHomepage', $perms, $preloadFolder);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @param array $params
     * @return array
     */
    private function preloadLessonPlan(array $params): array
    {
        try {
            $lessonPlan = $this->getLinkedAssessmentsMapper()->findById($params['lessonPlanId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'lessonPlan' => $lessonPlan,
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getErrorMapper(),
            $this->getCourseSpecificationMapper(),
            $this->getUnitSpecificationMapper(),
            $this->injector->get('assessmentDataSource'),
        );
    }

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return CourseSpecificationMapper
     */
    protected function getCourseSpecificationMapper()
    {
        return $this->getMapperFactory()->get(CourseSpecificationMapper::class);
    }

    /**
     * @return UnitSpecificationMapper
     */
    protected function getUnitSpecificationMapper()
    {
        return $this->getMapperFactory()->get(UnitSpecificationMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return Mapper|Folder\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }
}
