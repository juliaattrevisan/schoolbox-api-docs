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
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Auth code implementation.
 */
class AuthCode implements AuthCodeEntityInterface
{
    use TokenEntityTrait;
    use AuthCodeTrait;

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
