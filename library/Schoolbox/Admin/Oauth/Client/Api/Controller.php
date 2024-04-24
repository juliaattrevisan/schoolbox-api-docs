<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Oidc\Server\RelyingParty;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;
use Symfony\Component\HttpFoundation\Response;

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
        $this->persistAndRespond($model, $clientSecret);
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
        $this->persistAndRespond($newModel, null);
    }

    /**
     * Partially update a relying party's fields.
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
        $this->persistAndRespond($newModel, null);
    }

    /**
     * Delete a relying party.
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
     * Regenerate this relying party's client secret.
     *
     * @api   POST /adminv2/oauth/client/api/{id}/regenerateSecret
     * @since 24.0
     *
     * @param RelyingParty\Model $model
     */
    public function postRegenerateSecret(RelyingParty\Model $model): void
    {
        $clientSecret = RelyingParty\Generator\ClientSecret::generate();
        $model->setClientSecretHash(RelyingParty\Generator\ClientSecret::hash($clientSecret));
        $this->persistAndRespond($model, $clientSecret);
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

    /**
     * Persist `$model`, and respond with an array representation
     * (including `$clientSecret` if set) or an error if save failed.
     *
     * @param RelyingParty\Model $model
     * @param string|null        $newClientSecret
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    private function persistAndRespond(RelyingParty\Model $model, ?string $newClientSecret): void
    {
        try {
            $this->mapper->save($model);
        } catch (MapperException\CallbackException $e) {
            $this->sendJsonError($e->getMessage(), Response::HTTP_BAD_REQUEST);
            return;
        } catch (MapperException $e) {
            $this->sendJsonError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        // Reload the model, so the entire thing can be translated
        $model           = $this->mapper->findById($model->getId());
        $translatedModel = $this->translateModelToArray($model);
        if (isset($newClientSecret)) {
            $translatedModel = $translatedModel + ['client_secret' => $newClientSecret];
        }
        $this->response = $this->responseAsJson($translatedModel);
    }
}
