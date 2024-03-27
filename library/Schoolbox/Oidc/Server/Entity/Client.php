<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Schoolbox\Oidc\Server\RelyingParty\Model;

/**
 * Representation of an oAuth client.
 */
class Client implements ClientEntityInterface
{
    public function __construct(private Model $model)
    {
    }

    public function getIdentifier()
    {
        return $this->model->getClientId();
    }

    public function getName()
    {
        return $this->model->getName();
    }

    public function getRedirectUri()
    {
        // Need to add a redirect URI to mapper/model/form
    }

    /**
     * Is this client capable of storing the client secret securely?
     *
     * We can assume that it is, and therefore that we validate the client
     * secret when validating the client.
     *
     * @return true
     */
    public function isConfidential()
    {
        return true;
    }
}
