<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Example\Mapper;

/**
 * Module for the example API.
 */
class Module extends BaseModule
{
    /**
     * Base name for this module.
     *
     * This will be used as the prefix for route names, and the namespace for
     * Twig templates.
     */
    protected $basename = 'exampleApi';

    /**
     * Register routes for this module.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // Permission for each API route:
        // in practice, these routes will be more complicated than simply being
        // logged in but that will do for this example.
        $permission = new IsLoggedIn();

        // Preload for routes which contain an ID wildcard:
        // load the model for that ID, so that it is passed into the controller
        // method.
        $itemPreload = fn (array $params) => [
            'item' => $this->buildMapper()->findById($params['id']),
        ];

        // These are the standard RESTful API routes.
        $this->register(self::METHOD_GET, '', 'getList', $permission);
        $this->register(self::METHOD_GET, '/{id}', 'getItem', $permission, $itemPreload);
        $this->register(self::METHOD_POST, '', 'postItem', $permission);
        $this->register(self::METHOD_PUT, '/{id}', 'putItem', $permission, $itemPreload);
        $this->register(self::METHOD_PATCH, '/{id}', 'patchItem', $permission, $itemPreload);
        $this->register(self::METHOD_DELETE, '/{id}', 'deleteItem', $permission, $itemPreload);

//        // Sub-routes for managing relations from one object to another.
//        // Note that relations are not necessarily themselves able to be
//        // modified.
//        $this->register(self::METHOD_GET, '/{id}/related-items', 'getRelatedItemList');
//        $this->register(self::METHOD_GET, '/{id}/related-items/{relatedId}', 'getRelatedItem');
//        $this->register(self::METHOD_POST, '/{id}/related-items', 'postRelatedItem');
//        $this->register(self::METHOD_DELETE, '/{id}/related-items/{relatedId}', 'deleteRelatedItem');
    }

    /**
     * Build the controller, injecting all of its dependencies.
     *
     * For this example, we'll just use the mapper defined in the MVC example.
     */
    public function buildController(): Base
    {
        return new Controller($this->buildMapper());
    }

    /**
     * Convenience method for building a mapper.
     *
     * @return Mapper
     */
    private function buildMapper(): Mapper
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }
}
