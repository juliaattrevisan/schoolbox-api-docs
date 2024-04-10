<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Oidc\Server\RelyingParty;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;

/**
 * Controller actions for setting up oAuth clients (a.k.a. OpenID Connect
 * relying parties) in Schoolbox's admin system.
 */
class Controller extends Base
{
    /**
     * Model-to-array translator, only created when required.
     */
    private ?Translator\ModelToArray $modelToArray = null;

    /**
     * Array-to-model translator.
     */
    private Translator\ArrayToModel $arrayToModel;

    /**
     * Constructor
     *
     * @param RelyingParty\Mapper $mapper Mapper for saving relying parties.
     */
    public function __construct(private RelyingParty\Mapper $mapper)
    {
        $this->arrayToModel = new Translator\ArrayToModel();
    }

    /**
     * Get a list of relying parties.
     *
     * @api GET /adminv2/oauth/client/api
     * @since 24.0
     */
    public function getList(): void
    {
        $this->response = $this->responseAsJson(
            $this->mapper->findAll()->map($this->translateModelToArray(...))->reindex()
        );
    }

    /**
     * Get a single relying party.
     *
     * @api   GET /adminv2/oauth/client/api/{id}
     * @since 24.0
     *
     * @param RelyingParty\Model $model
     */
    public function getItem(RelyingParty\Model $model): void
    {
        $this->response = $this->responseAsJson($this->translateModelToArray($model));
    }

    /**
     * Create a relying party.
     *
     * This will return the client secret: note that this is the only point at
     * which the client secret is readable in plain text.
     *
     * @api   POST /adminv2/oauth/client/api
     * @since 24.0
     */
    public function postItem(): void
    {
        // Generate a new client ID+secret, return the new item with secret
        $data         = $this->request->getParsedBody();
        $clientSecret = RelyingParty\Generator\ClientSecret::generate();

        /** @var RelyingParty\Model $model */
        $model = call_user_func($this->arrayToModel, $data);
        $model
            ->setClientId(RelyingParty\Generator\ClientId::generate())
            ->setClientSecretHash(RelyingParty\Generator\ClientSecret::hash($clientSecret));
        $this->mapper->save($model);

        // Refetch model so that client credentials are rehydrated
        $model = $this->mapper->findById($model->getId());
        $this->response = $this->responseAsJson(
            $this->translateModelToArray($model)
            + ['clientSecret' => $clientSecret]
        );
    }

    /**
     * Update a relying party.
     *
     * @api   PUT /adminv2/oauth/client/api/{id}
     * @since 24.0
     *
     * @param RelyingParty\Model $model
     */
    public function putItem(RelyingParty\Model $model): void
    {
        $data = $this->request->getParsedBody();

        /** @var RelyingParty\Model $newModel */
        $newModel = call_user_func($this->arrayToModel, $data);
        $newModel->setId($model->getId());
        $this->mapper->save($newModel);
        $newModel = $this->mapper->findById($newModel->getId());
        $this->response = $this->responseAsJson($this->translateModelToArray($newModel));
    }

    /**
     * Partially update a relying party's fields.
     *
     * This is currently the same as `putItem`, because only the name can
     * change.
     *
     * @api   PATCH /adminv2/oauth/client/api/{id}
     * @since 24.0
     *
     * @param RelyingParty\Model $model
     */
    public function patchItem(RelyingParty\Model $model): void
    {
        $data = $this->request->getParsedBody();

        /** @var RelyingParty\Model $newModel */
        $newModel = call_user_func($this->arrayToModel, $data, $model);
        $this->mapper->save($newModel);
        $newModel = $this->mapper->findById($newModel->getId());
        $this->response = $this->responseAsJson($this->translateModelToArray($newModel));
    }

    /**
     * Delete a relying party.
     *
     * This is currently the same as `putItem`, because only the name can
     * change.
     *
     * @api   DELETE /adminv2/oauth/client/api/{id}
     * @since 24.0
     *
     * @param RelyingParty\Model $model
     */
    public function deleteItem(RelyingParty\Model $model): void
    {
        $this->mapper->delete($model);
        $this->response = $this->responseEmpty();
    }

    /**
     * Quick implementation of model-to-array conversion.
     *
     * @param RelyingParty\Model $model
     * @return array
     */
    private function translateModelToArray(RelyingParty\Model $model): array
    {
        $this->modelToArray ??= new Translator\ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            new UserTranslator(),
        );
        return call_user_func($this->modelToArray, $model);
    }
}
