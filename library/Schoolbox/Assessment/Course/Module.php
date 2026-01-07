<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Component_ConfigMapper;
use Component_InstanceMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\HomepageConfigMapper;
use Twig\Error\LoaderError;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Template\Mapper as CourseTemplateMapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;

/**
 * Class Module
 *
 * @package Schoolbox\Assessment\Course
 */
class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'course';

    /**
     * @return void
     */
    protected function registerRoutes()
    {
        $preloadFolders = function (array $params) {
            $folderMapper = $this->getFolderMapper();
            return [
                'courseFolder' => $folderMapper->findById($params['courseFolderId']),
                'unitFolder'   => $folderMapper->findById($params['unitFolderId']),
            ];
        };

        $preloadCourseSpec = function (array $params) {
            if (isset($params['courseSpec']) && $params['courseSpec'] instanceof CourseSpecificationModel) {
                return ['courseSpec' => $params['courseSpec']];
            }
            try {
                return ['courseSpec' => $this->getCourseSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['courseSpec' => $e->getDeletedObject()];
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        };

        $preloadUnitSpec = function (array $params) {
            try {
                return ['unitSpec' => $this->getUnitSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['unitSpec' => $e->getDeletedObject()];
            } catch (MapperException $e) {
                return ['unitSpec' => $this->getUnitSpecificationMapper()->createModel()];
            }
        };

        $preloadUnitImpl = function (array $params) {
            try {
                return ['unitImpl' => $this->getUnitImplementationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['unitImpl' => $e->getDeletedObject()];
            } catch (MapperException $e) {
                return ['unitImpl' => $this->getUnitImplementationMapper()->createModel()];
            }
        };

        $basicPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );

        /* Making sure the user has WRITE access to the unit specification folder
         */
        $hasFolderWriteAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\All(
                    new Rule\HasRole(Role::STAFF),
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_WRITE,
                        function (array $params) {
                            if (isset($params['unitSpec']) && $params['unitSpec'] instanceof UnitSpecificationModel) {
                                return $params['unitSpec']->getFolder()->getId();
                            }
                            try {
                                return $this->getUnitSpecificationMapper()->findById($params['id'])->getFolder()->getId();
                            } catch (MapperException\NotFound $e) {
                                throw new MapperException\NotFound($e->getMessage());
                            }
                        },
                        $this->getMapperFactory()->get(Access::class),
                    )
                )
            )
        );

        /* Making sure the user has WRITE access to the course specification folder
         */
        $hasCourseWriteAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\All(
                    new Rule\HasRole(Role::STAFF),
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_WRITE,
                        function (array $params): int {
                            if (isset($params['courseSpec']) && $params['courseSpec'] instanceof CourseSpecificationModel) {
                                return $params['courseSpec']->getFolder()->getId();
                            }
                            try {
                                return $this->getCourseSpecificationMapper()->findById($params['id'])->getFolder()->getId();
                            } catch (MapperException\NotFound $e) {
                                throw new MapperException\NotFound($e->getMessage());
                            }
                        },
                        $this->getMapperFactory()->get(Access::class),
                    ),
                )
            )
        );

        $this->register('GET', '/list', 'courseList', $basicPerms);
        $this->register('GET', '/list/ajax', 'courseListAjax', $basicPerms);
        $this->register('GET', '/new{/name}', 'newCourse', $basicPerms);
        $this->register('GET', '/parent{/folderId}', 'parentList', $basicPerms);
        $this->register('GET', '/{folderId}/nodes', 'listNodes', $basicPerms);
        $this->register('POST', '/finalise', 'persistCourse', $basicPerms);
        $this->register('GET', '/modify/{id}', 'modifyCourse', $hasCourseWriteAccess, $preloadCourseSpec);
        $this->register('POST', '/new', 'createCourse', $basicPerms);
        $this->register('POST', '/delete/{id}', 'deleteCourseSpecification', $basicPerms, $preloadCourseSpec);
        $this->register('POST', '/convert/{courseFolderId:\d+}/{unitFolderId:\d+}', 'convertFolders', $basicPerms, $preloadFolders);
        $this->register('POST', '/{id}/unitspecs/new', 'newUnit', $basicPerms, $preloadCourseSpec);
        $this->register('POST', '/modify/{id}', 'updateCourse', $basicPerms, $preloadCourseSpec);
        $this->register('POST', '/unitspecs/{id}/delete', 'deleteUnitSpecification', $hasFolderWriteAccess, $preloadUnitSpec);
        $this->register('POST', '/unitspecs/{id}/sequence', 'saveAssessmentSequence', $basicPerms, $preloadUnitSpec);
        $this->register('POST', '/reapply/{id}', 'reApplyFramework', $basicPerms, $preloadCourseSpec);
        $this->register('DELETE', '/units/{id}', 'deleteUnitImplementation', $basicPerms, $preloadUnitImpl);
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
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
        $folderMapper = $this->getMapperFactory()->get(FolderMapper::class);
        return new Controller(
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(CourseSpecificationMapper::class),
            $this->getMapperFactory()->get(UnitSpecificationMapper::class),
            $this->getMapperFactory()->get(UnitImplementationMapper::class),
            $folderMapper,
            $this->getMapperFactory()->get(Access::class),
            $this->getMapperFactory()->get(CourseConfigMapper::class),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(AssessmentMapper::class),
            $this->getMapperFactory()->get(CurriculumNodeMapper::class),
            $this->getMapperFactory()->get(CourseTemplateMapper::class),
            $this->getMapperFactory()->get(HomepageConfigMapper::class),
            $this->getMapperFactory()->get(Component_InstanceMapper::class),
            $this->getMapperFactory()->get(Component_ConfigMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Tile\Layout\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Tile\Mapper::class),
            Ancestry::getInstance($folderMapper),
        );
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
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
     * @return UnitImplementationMapper
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(UnitImplementationMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
