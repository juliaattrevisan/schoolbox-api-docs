<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
        $this->mapper = $mapper;
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
        $this->mapper->delete($entity);
    }

}
