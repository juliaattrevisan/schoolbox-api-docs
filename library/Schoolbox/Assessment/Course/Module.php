<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
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

        $preloadCourse = function (array $params) {
            try{
                return ['courseSpec' => $this->getCourseSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['courseSpec' => $e->getDeletedObject()];
            } catch (MapperException $e){
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        };

        $preloadUnit = function (array $params) {
            try{
                return ['unitSpec' => $this->getUnitSpecificationMapper()->findById($params['id'])];
            } catch (MapperException\Deleted $e) {
                return ['unitSpec' => $e->getDeletedObject()];
            } catch (MapperException $e){
                return ['unitSpec' => $this->getUnitSpecificationMapper()->createModel()];
            }
        };


        $this->register('GET',  '/modify/{id}',                                     'modifyCourse',   null, $preloadCourse);
        $this->register('POST', '/new',                                             'createCourse');
        $this->register('POST', '/delete/{id}',                          'deleteCourseSpecification', null, $preloadCourse);
        $this->register('POST', '/convert/{courseFolderId:\d+}/{unitFolderId:\d+}', 'convertFolders', null, $preloadFolders);
        $this->register('POST', '/{id}/units/new',                                  'newUnit',        null, $preloadCourse);
        $this->register('POST', '/modify/{id}',                                       'updateCourse', null, $preloadCourse);
        $this->register('POST', '/units/{id}/delete',                      'deleteUnitSpecification', null, $preloadUnit);
        $this->register('POST', '/units/{id}/sequence',                     'saveAssessmentSequence', null, $preloadUnit);
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
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class, [], [
                'setAssessmentMapperProxy'           => $injector->lazyGet('assessmentDataSource'),
                'setCourseSpecificationMapper'  => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
                'setCourseImplementationMapper' => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
                'setUnitSpecificationMapper'    => $this->getMapperFactory()->getLazy(UnitSpecificationMapper::class),
                'setUnitImplementationMapper'   => $this->getMapperFactory()->getLazy(UnitImplementationMapper::class),
                'setSubmissionBoxMapper'        => $this->getMapperFactory()->getLazy(SubmissionBoxMapper::class),
                'setFolderMapper'               => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'setComponentDefinitionMapper'  => $this->getMapperFactory()->getLazy(Component_DefinitionMapper::class),
                'setComponentInstanceMapper'    => $this->getMapperFactory()->getLazy(Component_InstanceMapper::class),
                'setTaskMapper'                 => $this->getMapperFactory()->getLazy(TaskMapper::class),
                'setHomepageConfigMapper'       => $this->getMapperFactory()->getLazy(HomepageConfigMapper::class),
                'setHomepageTemplateMapper'     => $this->getMapperFactory()->getLazy(HomepageTemplateMapper::class),
                'setAccess'                     => $this->getMapperFactory()->getLazy(Access::class),
                'setCourseConfigMapper'         => $this->getMapperFactory()->getLazy(CourseConfigMapper::class),
                'setUserAccessMapper'           => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                'setUserMapper'                 => $this->getMapperFactory()->getLazy(UserMapper::class),
                'setErrorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'setAssessmentMapper'           => $this->getMapperFactory()->getLazy(AssessmentMapper::class),
                'setCurriculumNodeMapper'       => $this->getMapperFactory()->getLazy(CurriculumNodeMapper::class),
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
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
