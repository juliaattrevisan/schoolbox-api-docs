<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * oAuth access token implementation.
 */
class AccessToken implements AccessTokenEntityInterface
{
    use TokenEntityTrait;
    use AccessTokenTrait;

    /**
     * Identifier for this access token.
     */
    private string $identifier;

    /**
     * Get the identifier.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Set the identifier.
     *
     * @param string $identifier
     * @return void
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }
}
