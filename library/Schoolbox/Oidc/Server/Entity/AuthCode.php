<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Entity;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Class AuthCode
 */
class AuthCode implements AuthCodeEntityInterface
{

    /**
     * @inheritDoc
     */
    public function getRedirectUri()
    {
        // TODO: Implement getRedirectUri() method.
    }

    /**
     * @inheritDoc
     */
    public function setRedirectUri($uri)
    {
        // TODO: Implement setRedirectUri() method.
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier()
    {
        // TODO: Implement getIdentifier() method.
    }

    /**
     * @inheritDoc
     */
    public function setIdentifier($identifier)
    {
        // TODO: Implement setIdentifier() method.
    }

    /**
     * @inheritDoc
     */
    public function getExpiryDateTime()
    {
        // TODO: Implement getExpiryDateTime() method.
    }

    /**
     * @inheritDoc
     */
    public function setExpiryDateTime(DateTimeImmutable $dateTime)
    {
        // TODO: Implement setExpiryDateTime() method.
    }

    /**
     * @inheritDoc
     */
    public function setUserIdentifier($identifier)
    {
        // TODO: Implement setUserIdentifier() method.
    }

    /**
     * @inheritDoc
     */
    public function getUserIdentifier()
    {
        // TODO: Implement getUserIdentifier() method.
    }

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        // TODO: Implement getClient() method.
    }

    /**
     * @inheritDoc
     */
    public function setClient(ClientEntityInterface $client)
    {
        // TODO: Implement setClient() method.
    }

    /**
     * @inheritDoc
     */
    public function addScope(ScopeEntityInterface $scope)
    {
        // TODO: Implement addScope() method.
    }

    /**
     * @inheritDoc
     */
    public function getScopes()
    {
        // TODO: Implement getScopes() method.
    }
}
