<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\Unit;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Error_Loader;
use Twig_Loader_Chain;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'courseImport';

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
                '/{courseId}/class/{classCode}{/componentId}' => 'getImportForm', // Import screen
                '/classes'                                    => 'searchClasses', // A list of Classes you can import into via selectize
                '/modify/{componentId}'                       => 'importModify',  // Modify the imported Course's Unit details
            ],
            'POST' => [
                '/classes'   => 'postClasses',   // result of step 1
                '/ownership' => 'postOwnership', // result of step 2
                '/units'     => 'postUnits',     // result of step 3
                '/tasks'     => 'postTasks',     // result of step 4
                '/delete'    => 'importDelete',  // TODO rename and add params
                '/restore'   => 'restoreCourse', // TODO rename and add params
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
     * Initializes any preloads on routes for this module.
     *
     * Preloads adjust route params before they are passed to the controller.
     * Usually, they're used to load a single item into the route's params,
     * which is then passed directly as a parameter to the action.
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // these routes require that the entity exist
        $this->addRoutePreload($matcher, 'getImportForm', function ($params) {

            try {
                $dmSpecUnitMapper   = $this->injector->get('mapperFactory')->get(\Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper::class);
                $dmSpecCourseMapper = $this->injector->get('mapperFactory')->get(\Schoolbox\Learning\Course\DataModel\Specification\Mapper::class);

                $unitTranslator   = new Course\DataModel\Specification\Unit\Translator();
                $courseTranslator = new Course\DataModel\Specification\Translator($unitTranslator);
                $courseSpecMapper = new Course\SpecificationMapperProxy($dmSpecCourseMapper, $courseTranslator, $dmSpecUnitMapper, $unitTranslator);

                $courseSpec = $courseSpecMapper->findById($params['courseId']);

            } catch (\Schoolbox\Mapper\Exception $e) {
                // null is ok
                $courseSpec = null;
            }

            try {
                /** @var Folder\Mapper $folderMapper */
                $folderMapper = $this->injector->get('mapperFactory')->get(Folder\Mapper::class);
                try {
                    $targetClass = $folderMapper->findByCode($params['classCode']);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $targetClass = $folderMapper->findById($params['classCode']);
                }
            } catch (\Schoolbox\Mapper\Exception $e) {
                // null is ok
                $targetClass = null;
            }

            return [
                'courseSpec'  => $courseSpec,
                'targetClass' => $targetClass,
                'componentId' => $params['componentId']
            ];

        });

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
        $this->addTemplatePath($templateLoader, __DIR__ . '/../templates');
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
                'setUnitSpecificationMapperProxy'   => $injector->lazyNew(Unit\SpecificationMapperProxy::class),
                'setFolderMapper'                   => $this->getMapperFactory()->getLazy(Folder\Mapper::class),
                'setCourseSpecificationMapperProxy' => $injector->lazyNew(Course\SpecificationMapperProxy::class),
                'setCourseImplementationMapperProxy' => $injector->lazyNew(Course\ImplementationMapperProxy::class),
                'setUserAccessMapper'               => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                'setAccess'                         => $injector->lazyNew(Access::class),
                'setUserMapper'                     => $this->getMapperFactory()->getLazy(UserMapper::class),
                'setAssessmentMapper'               => $injector->lazyGet('assessmentDataSource'),
                'setCourseImplementationFactory'    => $injector->lazyNew(Course\ImplementationFactory::class, [
                    'unitImplementationFactory' => $injector->lazyNew(Unit\ImplementationFactory::class)
                ])
            ])
        );

    }
}
