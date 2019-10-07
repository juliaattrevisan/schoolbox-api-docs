<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Role;
use Twig_Error_Loader;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use \Component_InstanceMapper;
use \Component_DefinitionMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'course';

    /**
     * Initializes any routes for this module, with a given base path.
     *
     * The base path for each route needs to match up to the name of the
     * controller in $this->initControllers(), so if its base path is
     * "user", there must be a controller in the DI container called
     * "controller:user"
     *
     * @param Router $router
     * @param string $basePath The base route for this module
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/list'                                => 'courseList',
                '/list/ajax'                           => 'courseListAjax',
                '/new{/name}'                          => 'newCourse',
                '/parent{/folderId}'                   => 'parentList',
                '/{folderId}/nodes'                    => 'listNodes',
            ],
            'POST' => [
                '/finalise'         => 'persistCourse',
                // AFAICT this was never used - I'm re-purposing it

            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

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
            try{
                return ['courseSpec' => $this->getCourseSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['courseSpec' => $e->getDeletedObject()];
            } catch (MapperException $e){
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        };

        $preloadUnitSpec = function (array $params) {
            try{
                return ['unitSpec' => $this->getUnitSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['unitSpec' => $e->getDeletedObject()];
            } catch (MapperException $e){
                return ['unitSpec' => $this->getUnitSpecificationMapper()->createModel()];
            }
        };

        $preloadUnitImpl = function (array $params) {
            try{
                return ['unitImpl' => $this->getUnitImplementationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['unitImpl' => $e->getDeletedObject()];
            } catch (MapperException $e){
                return ['unitImpl' => $this->getUnitImplementationMapper()->createModel()];
            }
        };

        $this->register('GET', '/modify/{id}', 'modifyCourse', null, $preloadCourseSpec);
        $this->register('POST', '/new', 'createCourse');
        $this->register('POST', '/delete/{id}', 'deleteCourseSpecification', null, $preloadCourseSpec);
        $this->register('POST', '/convert/{courseFolderId:\d+}/{unitFolderId:\d+}', 'convertFolders', null, $preloadFolders);
        $this->register('POST', '/{id}/unitspecs/new', 'newUnit', null, $preloadCourseSpec);
        $this->register('POST', '/modify/{id}', 'updateCourse', null, $preloadCourseSpec);
        $this->register('POST', '/unitspecs/{id}/delete', 'deleteUnitSpecification', null, $preloadUnitSpec);
        $this->register('POST', '/unitspecs/{id}/sequence', 'saveAssessmentSequence', null, $preloadUnitSpec);
        $this->register('DELETE', '/units/{id}', 'deleteUnitImplementation', null, $preloadUnitImpl);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            ));
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws \InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class, [
                'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
                'courseSpecificationMapper'  => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
                'unitSpecificationMapper'    => $this->getMapperFactory()->getLazy(UnitSpecificationMapper::class),
                'unitImplementationMapper'   => $this->getMapperFactory()->getLazy(UnitImplementationMapper::class),
                'folderMapper'               => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'homepageTemplateMapper'     => $this->getMapperFactory()->getLazy(HomepageTemplateMapper::class),
                'access'                     => $this->getMapperFactory()->getLazy(Access::class),
                'courseConfigMapper'         => $this->getMapperFactory()->getLazy(CourseConfigMapper::class),
                'userAccessMapper'           => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                'assessmentMapper'           => $this->getMapperFactory()->getLazy(AssessmentMapper::class),
                'curriculumNodeMapper'       => $this->getMapperFactory()->getLazy(CurriculumNodeMapper::class),
            ])
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
