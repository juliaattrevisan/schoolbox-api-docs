<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client\Api\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Oidc\Server\RelyingParty\Model;
use Schoolbox\User;

/**
 * Convert a oAuth client model to an array.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator  $routeGenerator Route generator: this, `$authorizer` and `$currentUser` are used to generate
     *                                   links to oAuth clients.
     * @param Authorizer $authorizer     Authorizer
     * @param User       $currentUser    Current user
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    /**
     * Convert `$model` to an array.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        $params = ['id' => $model->getId(),];
        return [
            'id'          => $model->getId(),
            'name'        => $model->getName(),
            'clientId'    => $model->getClientId(),
            'redirectUri' => $model->getRedirectUri(),
            '_links'      => [
                'put'    => $this->getRouteIfAuthorized('adminOauthClientApi.putItem', $params),
                'patch'  => $this->getRouteIfAuthorized('adminOauthClientApi.patchItem', $params),
                'delete' => $this->getRouteIfAuthorized('adminOauthClientApi.deleteItem', $params),
            ]
        ];
    }
}
