<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Twig_Loader_Chain;
use Schoolbox\UserMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'Module';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $routes = [
            'GET' => [
                '/ajax' => 'getSelectize',
                '/class/snapshot/{surveyId}' => 'getClassSnapshot',
            ],
            'POST' => [
            ]
        ];

        foreach ($routes as $httpMethod => $route) {
            foreach ($route as $path => $controllerMethod) {
                $this->register($httpMethod, $path, $controllerMethod);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }


    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '.*', function($params) {

            $preloads = [];

            if (isset($params['surveyId'])) {
                $preloads['survey'] = $this->getMapperFactory()->get(Mapper::class)->findById($params['surveyId']);
            }

            return $preloads;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'userMapper' => UserMapper::getInstance(),
            'folderMapper' => FolderMapper::getInstance(),
            'surveyMapper' => SurveyMapper::getInstance(),
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
    }
}
