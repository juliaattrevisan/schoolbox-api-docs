<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Controller\Restful;

use Alaress\Web\Controller\Base;
use Alaress\Web\Controller\Exception;

abstract class AbstractController extends Base implements IController
{


    /**
     * Populate the Response object with a form that provides a means to mutate the given (new) entity.
     * Only text/html is Accept-able.
     *
     * @param object $entity
     * @param object $context
     * @throws Exception
     * @return void
     */
    public function getCreateForm($entity, $context = null)
    {
        $this->getForm($entity, $context);
    }


    /**
     * Populate the Response object with a form that provides a means to mutate the given entity.
     * Only text/html is Accept-able.
     * If entity is deleted, respond with '410 Gone'
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function getModifyForm($entity)
    {
        $this->getForm($entity);
    }


    /**
     * @param object $entity
     * @param object $context
     * @return void
     */
    abstract protected function getForm($entity, $context = null);

    /**
     * Persist the given entity. If the persistence...
     *  - SUCCEEDS - populate the Response with a '201 Created' status and a Location header pointing to the freshly-created resource
     *  - FAILS    - respond appropriately - some specific codes to consider before falling back to generics
     *     - 422 Unprocessable Entity - general validation fail
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function createEntity($entity)
    {
        $this->persistEntity($entity);
    }


    /**
     * Persist the given pre-existing entity. If the persist...
     *  - SUCCEEDS - populate the Response with a '204 No Content' status and a Location header pointing to the updated resource
     *  - FAILS    - respond appropriately - some specific codes to consider before falling back to generics
     *     - 409 Conflict             - specific validation fail: someone else modified this entity under your feet
     *     - 422 Unprocessable Entity - general validation fail
     *     - 410 Gone                 - entity is deleted (soft or otherwise)
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function updateEntity($entity)
    {
        $this->persistEntity($entity);
    }


    /**
     * @param object $entity
     * @return void
     */
    abstract protected function persistEntity($entity);
}
