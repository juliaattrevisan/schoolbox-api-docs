<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * A representation of an allowable scope.
 */
class Scope implements ScopeEntityInterface
{
    /**
     * Constructor
     *
     * @param string $identifier
     */
    public function __construct(private string $identifier)
    {
    }

    /**
     * Return the scope's identifier.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Just serialize the identifier I guess?
     */
    public function jsonSerialize(): string
    {
        return $this->getIdentifier();
    }
}
