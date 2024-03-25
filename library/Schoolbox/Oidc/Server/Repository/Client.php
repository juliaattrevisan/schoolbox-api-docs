<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * OIDC client repository interface implementation: fetches and validates a
 * client entity out of what is set up in the database.
 */
class Client implements ClientRepositoryInterface
{
    public function getClientEntity($clientIdentifier)
    {
        // TODO: Implement getClientEntity() method.
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        // TODO: Implement validateClient() method.
    }
}
