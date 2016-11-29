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
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Learning\Course;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;

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
                '/unit/new'         => 'newUnit',
                // AFAICT this was never used - I'm re-purposing it
                '/unit/delete/{id}' => 'deleteUnitSpecification',
                '/new'              => 'createCourse',
                '/modify/{id}'      => 'updateCourse',
                '/finalise'         => 'persistCourse',
                // AFAICT this was never used - I'm re-purposing it
                '/delete/{id}'      => 'deleteCourseSpecification',
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
                return ['course' => $this->getCourseSpecificationMapper()->findById($params['id'])];
            } catch (MapperException $e){
                return ['course' => $this->getCourseSpecificationMapper()->createModel()];
            }
        };

        $this->register('POST', '/convert/{courseFolderId:\d+}/{unitFolderId:\d+}', 'convertFolders', null, $preloadFolders);
        $this->register('GET',  '/modify/{id}',                                     'modifyCourse',   null, $preloadCourse);
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
            $injector->lazyNew(
                Controller::class,
                [
                    'assessmentDataSource'          => $injector->lazyGet('assessmentDataSource'),
                    'courseSpecificationDataSource' => $injector->lazyGet('courseSpecificationDataSource'),
                    'unitSpecificationDataSource'   => $injector->lazyGet('unitSpecificationDataSource'),
                ]
            )
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
}
