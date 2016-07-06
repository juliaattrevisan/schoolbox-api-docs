<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\Result;

use Alaress\Web\Router\PathMatcher;
use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;

class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule      $context
     */
    public function __construct(ContainerInterface $injector, RestfulModule $context)
    {
        parent::__construct($injector, Mapper::getInstance(), 'results', $context, 'sportsfixturesResult');
    }

    /**
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer) {

        /* Permissions requirements for being a Sports Administrator
         */
        $isSportsAdministrator = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_SPORTS_ADMINISTRATOR, UserAccess::ACCESS_WRITE)
            )
        );

        /* Apply route permissions here as you add them to initRoutes()
         * Note that if the perms change you need to log out/log in to refresh
         */
        $this->addRoutePermission($authorizer, '.*', $isSportsAdministrator);
    }

    /**
     * {@inheritdoc}
     *
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);

        $collection = $this->getCollectionName();

        // if this module has context (ie. a containing entity) then create a prefix to be used with contextual routes
        $context = $this->context
            ? "{$this->context->getCollectionName()}/{contextId}/"
            : '';

        $routes = [
            'GET' => [
                "/$context$collection/{teamId}/new" => 'getCreateFormForTeam',
                "/$context$collection/{teamId}"     => 'getResultForTeam',
            ],
        ];

        foreach ($routes['GET'] as $path => $controllerMethod) {
            $this->addGetRoute($router, $controllerMethod, $basePath, $path)->setTokens([ 'contextId' => '\d+', 'entityId' => '\d+' ]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        parent::initRoutePreloads($matcher);

        // these routes require that the entity exist
        $this->addRoutePreload($matcher, '(?:getCreateFormForTeam)', function ($params) {
            return [
                'entity' => $this->mapper->createModel(),
                'context' => $this->findContextById($params['contextId']),
                'team' => TeamMapper::getInstance()->findById($params['teamId'])
            ];
        });

        $this->addRoutePreload($matcher, '(?:createEntity)', function () {
            return [
                'entity' => $this->mapper->createModel(),
                'team' => TeamMapper::getInstance()->createModel()
            ];
        });

        /* Result Page for a particular Team
         */
        $this->addRoutePreload($matcher, '(?:getResultForTeam)', function ($params) {
            return [
                'context' => $this->findContextById($params['contextId']),
                'team' => TeamMapper::getInstance()->findById($params['teamId'])
            ];
        });
    }
}
