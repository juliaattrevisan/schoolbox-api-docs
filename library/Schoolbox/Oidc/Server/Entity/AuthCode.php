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
 * Auth code implementation.
 */
class AuthCode implements AuthCodeEntityInterface
{
    /**
     * This auth code's redirect URI, if present.
     */
    private ?string $redirectUri = null;

    /**
     * This auth code's expiry.
     */
    private DateTimeImmutable $expiryDateTime;

    /**
     * The user identifier for this auth code, if there is one.
     */
    private int|string|null $userIdentifier;

    /**
     * The oAuth client this auth code is associated with.
     */
    private ClientEntityInterface $client;

    /**
     * The available scopes for this auth code.
     *
     * @var ScopeEntityInterface[]
     */
    private array $scopes = [];

    /**
     * Constructor
     *
     * @param string $identifier The unique identifier for this auth code.
     */
    public function __construct(private string $identifier)
    {
    }

    /**
     * @return string|null
     */
    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    /**
     * @param string|null $uri
     * @return void
     */
    public function setRedirectUri($uri): void
    {
        $this->redirectUri = $uri;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     * @return void
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    /**
     * @param DateTimeImmutable $dateTime
     * @return void
     */
    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expiryDateTime = $dateTime;
    }

    /**
     * @return int|string|null
     */
    public function getUserIdentifier(): int|string|null
    {
        return $this->userIdentifier;
    }

    /**
     * @param string $identifier
     * @return void
     */
    public function setUserIdentifier($identifier): void
    {
        $this->userIdentifier = $identifier;
    }

    /**
     * @return ClientEntityInterface
     */
    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    /**
     * @param ClientEntityInterface $client
     * @return void
     */
    public function setClient(ClientEntityInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * @param ScopeEntityInterface $scope
     * @return void
     */
    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->scopes[] = $scope;
    }

    /**
     * @return ScopeEntityInterface[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}
