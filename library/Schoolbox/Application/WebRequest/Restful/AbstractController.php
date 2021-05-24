<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\WebRequest\Restful;

use Alaress\Web\Controller\Exception;
use Schoolbox\Mapper;

abstract class AbstractController extends \Alaress\Web\Controller\Restful\AbstractController
{

    /** @var Mapper */
    protected $mapper;

    /**
     * AbstractController constructor.
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->setMapper($mapper);
    }

    /**
     * Delete the given entity. Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     * On success, respond with '204 No Content' and a Location header to the list
     * On failure, respond appropriately
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function deleteEntity($entity)
    {
        $this->getMapper()->delete($entity);
        $this->getResponse()->status->setCode(204);
    }

    /* Getters/Setters
     */

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param Mapper $mapper
     */
    protected function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }
}
