<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\Container;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Router;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Schoolbox\Assessment\Term;
use Schoolbox\UserMapper;
use Schoolbox\Error;
use DueWork_ErrorMapper;
use \Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilter;


class Module extends BaseModule
{
    protected $basename = 'learningMarkbook';

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws NotFound If a class was specified but it does not exist
     */
    public function registerRoutes()
    {
        $isStaffOrSuper = new Rule\Any(
            new Rule\HasRole(Role::STAFF),
            new Rule\IsSuperuser()
        );

        // TODO find a way to specify routes to not collide (this next line still matches '/export')
//        $this->register('GET',  '{/userId:\d}', 'getMarkbook',       $isStaffOrSuper, $preload);
        $this->register('POST', '',             'postMarkbook',      $isStaffOrSuper);  //AJAX in the report

        $this->register('GET',  '/export',      'getMarkbookExport', $isStaffOrSuper);


    }


    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getMarkbook', $basePath, '{/userId}');
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to find the user mapper
     * @throws NotFound If no user found for specified ID
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getMarkbook)',
            function ($params) {
                if(null !== $params['userId']){
                    try{
                        return ['viewee' => $this->getUserMapper()->findById($params['userId'])];
                    } catch (Mapper\Exception $e){
                        throw new NotFound("No user found for id: {$params['userId']}");
                    }
                }
                return [];
            }
        );

        $this->register('GET',  '/class/{folderId:\d+}{/userId}', 'getMarkbookForClass', $isStaffOrSuper, $preload);

        $this->register('GET',  '{/userId}',        'getMarkbook',       $isStaffOrSuper, $preload);
        $this->register('POST', '/{userId}',        'postMarkbook',      $isStaffOrSuper, $preload);  //AJAX in the report

        $this->register('GET',  '/{userId}/export', 'getMarkbookExport', $isStaffOrSuper, $preload);
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
     * @param Container $injector
     * @throws \InvalidArgumentException If class not found
     * @throws ServiceNotFound
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->getBasename(),
            $injector->lazyNew(Controller::class, [
                'defaultGradeGroup'          => $this->getInjector()->get('defaultGradeGroup'),
                'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
                'termMapper'                 => $this->getMapperFactory()->getLazy(Term\Mapper::class),
                'dueWorkErrorMapper'         => $this->getMapperFactory()->getLazy(DueWork_ErrorMapper::class),
                'access'                     => $this->getMapperFactory()->getLazy(Access::class), // Has a static 'getInstance()' method
                'assessmentIdFinder'         => $injector->lazy(function(){
                    return AssessmentFilter\IdFinder::getInstance($this->getMapperFactory()->get(Assessment\Mapper::class));
                }),
                'filterFactoryFactory'       => $injector->lazy(function(){
                    return new AssessmentFilter\FactoryFactory($this->getMapperFactory());
                }),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|Folder\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Mapper|Term\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }

    /**
     * @return Mapper|Error\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }
}
