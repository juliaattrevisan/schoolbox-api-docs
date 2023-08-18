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
use Component_InstanceMapper;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LinkedAssessmentsMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access as AccessMapper;
use Schoolbox\Role;

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

        /**
         * All users should be able to view the classes lesson plans if they can see the class page
         */
        $permsAll = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasHomepageAccess(UserAccess::ACCESS_READ, function ($params) {
                return $params['folderId'];
            }, $this->getAccessMapper())
        );

        $preloadCourseSpec = function (array $params) {
            try {
                $courseSpec = $this->getCourseSpecificationMapper()->findById($params['specId']);
            } catch (MapperException\Deleted $e) {
                $courseSpec = $e->getDeletedObject();
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
            // there may be no courses on class pages yet, so ignore these entries.
            $courseImplementation = null;
            if (null !== $params['implId']) {
                try {
                    $courseImplementation = $this->getCourseImplementationMapper()->findById($params['implId']);
                } catch (MapperException\Deleted $e) {
                    $courseImplementation = $e->getDeletedObject();
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }
            }
            return [
                'courseSpec' => $courseSpec,
                'courseImpl' => $courseImplementation,
            ];
        };

        $preloadFolder = function (array $params) {
            try {
                return ['folder' => $this->getFolderMapper()->findById($params['folderId'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound();
            }
        };

        // Get all activities contained within a course
        $preloadLessonPlan = function ($params) {
            try {
                $lessonPlan = $this->getLinkedAssessmentsMapper()->findById($params['lessonPlanId']);
            } catch (MapperException $e) {
                throw new NotFound();
            }

            return [
                'lessonPlan' => $lessonPlan,
            ];
        };

        // Get all activities contained within a course
        $this->register('GET', '/course/{id}', 'getCourse', $perms, $preloadCourseSpec);
        // get lessonplans for homepage
        $this->register('GET', '/class/{folderId:\d+}', 'getHomepage', $permsAll, $preloadFolder);
        $this->register('GET', '/class/{folderId:\d+}/component/{componentId:\d+}', 'getHomepageComponent', $permsAll, $preloadFolder);
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
    public function buildController(): Base
    {
        return new Controller(
            $this->getUnitSpecificationMapper(),
            $this->getUnitImplementationMapper(),
            $this->getCourseImplementationMapper(),
            $this->getLessonPlanMapper(),
            $this->injector->get('assessmentDataSource'),
            $this->getComponentInstanceMapper(),
            $this->getAccessMapper(),
        );
    }

    /**
     * @return CourseSpecificationMapper
     */
    protected function getCourseSpecificationMapper(): CourseSpecificationMapper
    {
        return $this->getMapperFactory()->get(CourseSpecificationMapper::class);
    }
    /**
     * @return CourseImplementationMapper
     */
    protected function getCourseImplementationMapper(): CourseImplementationMapper
    {
        return $this->getMapperFactory()->get(CourseImplementationMapper::class);
    }

    /**
     * @return UnitSpecificationMapper
     */
    protected function getUnitSpecificationMapper(): UnitSpecificationMapper
    {
        return $this->getMapperFactory()->get(UnitSpecificationMapper::class);
    }

    /**
     * @return UnitImplementationMapper
     */
    protected function getUnitImplementationMapper(): UnitImplementationMapper
    {
        return $this->getMapperFactory()->get(UnitImplementationMapper::class);
    }

    /**
     * @return LessonPlanMapper
     */
    protected function getLessonPlanMapper(): LessonPlanMapper
    {
        return $this->getMapperFactory()->get(LessonPlanMapper::class);
    }

    /**
     * @return Folder\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getFolderMapper(): Folder\Mapper
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return ErrorMapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getErrorMapper(): ErrorMapper
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return Component_InstanceMapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getComponentInstanceMapper(): Component_InstanceMapper
    {
        return $this->getMapperFactory()->get(Component_InstanceMapper::class);
    }

    /**
     * @return AccessMapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getAccessMapper(): AccessMapper
    {
        return $this->getMapperFactory()->get(AccessMapper::class);
    }

    /**
     * @return Mapper|LinkedAssessmentsMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getLinkedAssessmentsMapper()
    {
        return $this->getMapperFactory()->get(LinkedAssessmentsMapper::class);
    }
}
