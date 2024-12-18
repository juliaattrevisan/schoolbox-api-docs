<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\Example\Mapper;
use Schoolbox\Example\Model;

/**
 * Controller for the example API.
 */
class Controller extends Base
{
    private const LIMIT = 10;

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
        $cursor = new MapperCursor(
            $this->mapper,
            self::LIMIT,
            [
                'created_at' => $this->mapper::SORT_ORDER_DESC,
                'id'         => $this->mapper::SORT_ORDER_DESC,
            ]
        );
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
