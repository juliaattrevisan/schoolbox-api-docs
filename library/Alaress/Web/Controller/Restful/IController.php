<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Controller\Restful;

use Alaress\Web\Controller\Exception;

interface IController
{

    /**
     * Populate the Response object with a list of entities:
     *  - in the format provided in the request Accepts header
     *  - optionally based on search parameters provided in the query string
     *
     * @param object $context
     * @throws Exception
     * @return void
     */
    public function getList($context = null);

    /**
     * Populate the Response object with a form that provides a means to mutate the given (new) entity.
     * Only text/html is Accept-able.
     *
     * @param object $entity
     * @param object $context
     * @throws Exception
     * @return void
     */
    public function getCreateForm($entity, $context = null);

    /**
     * Populate the Response object with a form that provides a means to mutate the given entity.
     * Only text/html is Accept-able.
     * If entity is deleted, respond with '410 Gone'
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function getModifyForm($entity);

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function getEntity($entity);

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
    public function createEntity($entity);

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
    public function updateEntity($entity);

    /**
     * Delete the given entity. Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     * On success, respond with '204 No Content' and a Location header to the list
     * On failure, respond appropriately
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function deleteEntity($entity);
}
