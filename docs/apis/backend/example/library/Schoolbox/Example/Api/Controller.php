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

    public function getItem(Model $item): void
    {
        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($item));
    }

    public function postItem(): void
    {
        $arrayToModel = new Translator\ArrayToModel();
        $model        = $arrayToModel(
            $this->request->getParsedBody(),
            $this->mapper->createModel()->setUser(new User())
        );
        $this->mapper->save($model);

        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($model));
    }

    public function putItem(Model $item): void
    {
        $arrayToModel = new Translator\ArrayToModel();
        $item         = $arrayToModel(
            $this->request->getParsedBody(),
            $this->mapper->createModel($item->getId())->setUser(new User())
        );
        $this->mapper->save($item);

        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($item));
    }

    public function patchItem(Model $item): void
    {
        $arrayToModel = new Translator\ArrayToModel();
        $item         = $arrayToModel($this->request->getParsedBody(), $item);
        $this->mapper->save($item);

        $modelToArray   = $this->buildModelToArrayTranslator();
        $this->response = $this->responseAsJson($modelToArray($item));
    }

    public function deleteItem(Model $item): void
    {
        $this->mapper->delete($item);
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
