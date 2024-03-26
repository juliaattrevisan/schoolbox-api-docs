<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Oidc\Server\RelyingParty;

/**
 * Controller actions for setting up oAuth clients (a.k.a. OpenID Connect
 * relying parties) in Schoolbox's admin system.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param RelyingParty\Mapper $mapper Mapper for saving relying parties.
     */
    public function __construct(private RelyingParty\Mapper $mapper)
    {
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
            $this->mapper->findAll()
                 ->map(fn (RelyingParty\Model $model) => [
                     'id'       => $model->getId(),
                     'name'     => $model->getName(),
                     'clientId' => $model->getClientId(),
                 ])
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
        $this->response = $this->responseAsJson([
            'id'       => $model->getId(),
            'name'     => $model->getName(),
            'clientId' => $model->getClientId(),
        ]);
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
        $model        = $this->mapper->createModel()
            ->setName($data['name'])
            ->setClientId(RelyingParty\Generator\ClientId::generate())
            ->setClientSecretHash(RelyingParty\Generator\ClientSecret::hash($clientSecret));
        $this->mapper->save($model);
        $this->response = $this->responseAsJson([
            'id'           => $model->getId(),
            'name'         => $model->getName(),
            'clientId'     => $model->getClientId(),
            'clientSecret' => $clientSecret,
        ]);
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
        // Only the name can be updated
        $data = $this->request->getParsedBody();
        if (isset($data['name'])) {
            $model = $model->setName($data['name']);
        }
        $this->mapper->save($model);
        $this->response = $this->responseEmpty();
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
        $this->putItem($model);
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
}
