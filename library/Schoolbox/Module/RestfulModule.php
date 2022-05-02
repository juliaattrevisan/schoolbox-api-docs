<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Module;

use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Psr\Container\ContainerInterface;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Alaress\Module\Module as AlaressModule;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Class RestfulModule
 *
 * This Module abstraction depends on the associated Controller being an implementation of Alaress\Web\Controller\IRestfulController
 * It defines routes for default crud actions. You can easily extend its functionality as required, this is just the Restful base.
 *
 * @package Schoolbox\Module
 */
abstract class RestfulModule extends AlaressModule
{

    /** @var string */
    protected $collection;

    /** @var RestfulModule|null */
    protected $context = null;

    /** @var string */
    protected $mapperClass;

    /**
     * @param ContainerInterface $injector
     * @param class-string       $mapper
     * @param string             $collectionName
     * @param RestfulModule|null $context
     * @param string             $basename
     */
    public function __construct(
        ContainerInterface $injector,
        string $mapper,
        string $collectionName,
        RestfulModule $context = null,
        string $basename = ''
    ) {
        parent::__construct($injector, $basename);
        $this->context     = $context;
        $this->collection  = $collectionName;
        $this->mapperClass = $mapper;
    }

    /**
     * @return RestfulModule|null
     */
    public function getContext(): ?RestfulModule
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collection;
    }


    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->getMapperFactory()->get($this->mapperClass);
    }


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
        $collection = $this->getCollectionName();

        // if this module has context (ie. a containing entity) then create a prefix to be used with contextual routes
        $context = $this->context
            ? "{$this->context->getCollectionName()}/{contextId}/"
            : '';

        $routes = [
            'GET' => [
                "/$context$collection"           => 'getList',
                "/$context$collection/new"       => 'getCreateForm',
                "/$collection/{entityId}"        => 'getEntity',
                "/$collection/{entityId}/edit"   => 'getModifyForm',
            ],
            'POST' => [
                "/$collection"                   => 'createEntity',
                "/$collection/{entityId}"        => 'updateEntity',
                "/$collection/{entityId}/delete" => 'deleteEntity',
            ]
        ];

        foreach ($routes['GET'] as $path => $controllerMethod) {
            $this->addGetRoute($router, $controllerMethod, $basePath, $path)->setTokens([ 'contextId' => '\d+|\*', 'entityId' => '\d+' ]);
        }

        foreach ($routes['POST'] as $path => $controllerMethod) {
            $this->addPostRoute($router, $controllerMethod, $basePath, $path)->setTokens([ 'contextId' => '\d+', 'entityId' => '\d+' ]);
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
        $this->addRoutePreload($matcher, '(?:getEntity|updateEntity|deleteEntity|getModifyForm)', function ($params) {
            return [
                'entity' => $this->findEntityById($params['entityId'])
            ];
        });

        $this->addRoutePreload($matcher, '(?:createEntity)', function () {
            return [
                'entity' => $this->getMapper()->createModel()
            ];
        });

            // these routes will just spawn new entities, but if context exists, it is required
        $this->addRoutePreload($matcher, '(?:getCreateForm)', function ($params) {
            $args = [
                'entity' => $this->getMapper()->createModel()
            ];

            if ($this->context) {
                // only preload context if there actually is one
                $args['context'] = $this->findContextById($params['contextId']);
            }

            return $args;
        });

        if ($this->context) {
            // only preload context if there actually is one
            $this->addRoutePreload($matcher, '(?:getList)', function ($params) {
                return [
                    'context' => $this->findContextById($params['contextId'])
                ];
            });
        }
    }


    /**
     * @param int $id
     * @throws \Schoolbox\Application\Exception\Exception
     * @return object
     */
    protected function findEntityById($id)
    {
        return $this->findById($this->getMapper(), $id);
    }

    /**
     * @param int $id
     * @throws \Schoolbox\Application\Exception\Exception
     * @return object
     */
    protected function findContextById($id)
    {
        return $this->findById($this->context->getMapper(), $id);
    }


    /**
     * @param Mapper $mapper
     * @param int    $id
     * @throws \Schoolbox\Application\Exception\Exception
     * @return object
     */
    private function findById(Mapper $mapper, $id)
    {
        if ($id == "*") {
            return null;
        }

        try {
            return $mapper->findById($id);
        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }
}
