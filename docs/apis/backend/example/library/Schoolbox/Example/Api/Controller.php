<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Example\Mapper;
use Schoolbox\Example\Model;

/**
 * Controller for the example API.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(private Mapper $mapper)
    {
    }

    public function getList(): void
    {
        // TODO add search+cursor for this item
        // TODO add model-to-array
    }

    public function getItem(Model $model): void
    {
        $this->response = $this->responseAsJson([]);
    }

    public function postItem(): void
    {
        // TODO add array-to-model
    }

    public function putItem(Model $model): void
    {
    }

    public function patchItem(Model $model): void
    {
    }

    public function deleteItem(Model $model): void
    {
        $this->mapper->delete($model);
        $this->response = $this->responseEmpty();
    }
}
