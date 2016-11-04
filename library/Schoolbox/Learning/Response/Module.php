<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Response;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use \External_UserInterface;

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningResponse';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [

                /* Get a list of Responses for a particular Student and Assessment
                 * Presented as a more succinct Submission History
                 */
                '/assessment/{assessmentId}/{studentId}/responses' => 'getResponseList',

                /* View a Response in the Inline Grader
                 */
                '/responses/{responseId}'                          => 'getParticularResponse',

                /* View a Response File's plagscan report
                 */
                '/responses/plagscan/{plagscanId}'                 => 'getPlagscanReport',
            ],
            'POST' => []
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:getResponseList)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            ));

        $ruleRegexp = '(?:getResponse)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            ));
    }


    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '(?:getResponseList)', function ($params) {
            try {
                $student = $this->getUserMapper()->findById($params['studentId']);
            } catch (MapperException $e) {
                $student = $this->getUserMapper()->createModel();
            }

            return [
                'assessment' => $this->getAssessmentMapperProxy()->findById($params['assessmentId']),
                'student'    => $student,
            ];
        });

        $this->addRoutePreload($matcher, '(?:getParticularResponse)', function ($params) {
            $response = $params['responseId'];

            return [
                'response' => $response,
            ];
        });
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
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }
}
