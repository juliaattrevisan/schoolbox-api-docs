<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
 
namespace Schoolbox\SportsFixtures\Match;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Aura\Di\ContainerInterface;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use \Schoolbox\Permission\Access\UserAccess;
use Schoolbox\SportsFixtures\Match\Model as Match;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;

class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule      $context
     */
    public function __construct(ContainerInterface $injector, RestfulModule $context)
    {
        parent::__construct($injector, Mapper::getInstance(), 'matches', $context, 'sportsfixturesMatch');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);

        $collection = $this->getCollectionName();

        $context = "{$this->context->getCollectionName()}/{contextId}/";

        $routes = [
            'GET' => [
                "/{$context}season/{seasonId}/$collection/new" => 'getInSeasonForm',
            ],
        ];

        foreach ($routes['GET'] as $path => $controllerMethod) {
            $this->addGetRoute($router, $controllerMethod, $basePath, $path)->setTokens([ 'contextId' => '\d+|\*', 'seasonId' => '\d+', 'entityId' => '\d+' ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        parent::initRoutePreloads($matcher);

        // these routes will just spawn new entities, but if context exists, it is required
        $this->addRoutePreload($matcher, '(?:getInSeasonForm)', function ($params) {
            /** @var Match $match */
            $match = $this->mapper->createModel();
            $match->setDivision(DivisionMapper::getInstance()->findById($params['contextId']));
            $match->setSeason(SeasonMapper::getInstance()->findById($params['seasonId']));
            return [
                'match'    => $match,
            ];
        });
    }

    /**
     * {@inheritdoc}
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

}
