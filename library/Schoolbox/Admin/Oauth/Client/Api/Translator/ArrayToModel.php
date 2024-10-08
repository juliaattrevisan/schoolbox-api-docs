<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client\Api\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Oidc\Server\RelyingParty\Model;
use Schoolbox\User;

/**
 * Convert a oAuth client model to an array.
 */
class ArrayToModel extends Base
{
    /**
     * Convert `$array` to a model.
     *
     * @param array      $array
     * @param Model|null $model
     * @return Model
     */
    public function __invoke(array $array, object $model = null): object
    {
        $model ??= new Model();
        $model = $this->setScalar($array, 'name', $model, 'setName');
        $model = $this->setScalar($array, 'redirectUri', $model, 'setRedirectUri');
        $model = $this->setScalar($array, 'backChannelLogoutUri', $model, 'setBackChannelLogoutUri');
        $model = $this->setModel(
            $array,
            'clientCredentialsUser',
            'id',
            $model,
            'setClientCredentialsUser',
            new User(),
            'setId',
        );
        return $model;
    }
}
