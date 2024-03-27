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
    /**
     * Constructor
     *
     * @param Model $model The model which stores oAuth client configuration.
     */
    public function __construct(private Model $model)
    {
    }

    /**
     * Returns the identifier of the client.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->model->getClientId();
    }

    /**
     * Returns the name of the client.
     *
     * @return string
     */
    public function getName()
    {
        return $this->model->getName();
    }

    /**
     * Returns the client's redirect URI.
     *
     * @return string
     */
    public function getRedirectUri()
    {
        return $this->model->getRedirectUri();
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
