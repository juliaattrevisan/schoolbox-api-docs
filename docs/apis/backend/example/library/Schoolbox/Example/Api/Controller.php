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
use Schoolbox\User;

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
        $cursor = new MapperCursor(
            $this->mapper,
            self::LIMIT,
            [
                'created_at' => $this->mapper::SORT_ORDER_DESC,
                'id'         => $this->mapper::SORT_ORDER_DESC,
            ],
        );
        $results        = $cursor->retrieveResults($this->requestQuery->get('cursor'));
        $this->response = $this->responseAsJson([
            'data'     => $results->map($this->buildModelToArrayTranslator()),
            'metadata' => [
                'cursor' => [
                    'current' => $cursor->current($results),
                    'next'    => $cursor->next($results),
                ],
                'count' => $cursor->retrieveResultCount(),
            ],
        ]);
    }

    public function getItem(Model $model): void
    {
        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($model));
    }

    public function postItem(): void
    {
        $arrayToModel = new Translator\ArrayToModel();
        $model        = $arrayToModel($this->request->getParsedBody());
        $this->mapper->save($model);

        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($model));
    }

    public function putItem(Model $model): void
    {
        $arrayToModel = new Translator\ArrayToModel();
        $model        = $arrayToModel($this->request->getParsedBody(), $this->mapper->createModel($model->getId()));
        $this->mapper->save($model);

        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($model));
    }

    public function patchItem(Model $model): void
    {
        $arrayToModel = new Translator\ArrayToModel();
        $model        = $arrayToModel($this->request->getParsedBody(), $model);
        $this->mapper->save($model);

        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($model));
    }

    public function deleteItem(Model $model): void
    {
        $this->mapper->delete($model);
        $this->response = $this->responseEmpty();
    }

    private function buildModelToArrayTranslator(): Translator\ModelToArray
    {
        return new Translator\ModelToArray(
            $this->getRouteGenerator(),
            $this->authorizer,
            $this->getUser(),
            new User\Translator\Author\ModelToArray(),
        );
    }
}
