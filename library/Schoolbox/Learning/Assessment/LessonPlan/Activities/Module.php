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
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper\Exception as MapperException;
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
         * All users should be able to view the classes lesson plans
         */
        $permsAll = new Rule\IsLoggedIn();

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
        $this->register('GET', '/course/{specId:\d+}{/implId:\d+}', 'getCourse', $perms, $preloadCourseSpec);

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
            $this->getLessonPlanMapper(),
            $this->injector->get('assessmentDataSource'),
            $this->getComponentInstanceMapper(),
        );
    }

    /**
     * @return CourseSpecificationMapper
     */
    protected function getCourseSpecificationMapper()
    {
        return $this->getMapperFactory()->get(CourseSpecificationMapper::class);
    }
    /**
     * @return CourseImplementationMapper
     */
    protected function getCourseImplementationMapper()
    {
        return $this->getMapperFactory()->get(CourseImplementationMapper::class);
    }

    /**
     * @return UnitSpecificationMapper
     */
    protected function getUnitSpecificationMapper()
    {
        return $this->getMapperFactory()->get(UnitSpecificationMapper::class);
    }

    /**
     * @return UnitImplementationMapper
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(UnitImplementationMapper::class);
    }

    /**
     * @return LessonPlanMapper
     */
    protected function getLessonPlanMapper()
    {
        return $this->getMapperFactory()->get(LessonPlanMapper::class);
    }

    /**
     * @return Mapper|Folder\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
    /**
     * @return Mapper|\Component_InstanceMapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getComponentInstanceMapper()
    {
        return $this->getMapperFactory()->get(\Component_InstanceMapper::class);
    }
}
