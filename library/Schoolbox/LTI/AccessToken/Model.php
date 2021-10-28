<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Alaress_Date_Format;
use Schoolbox\LTI\Provider\Model as Tool;
use Schoolbox\User;

/**
 * LTI access token model
 */
class Model
{
    /**
     * The token's identifier.
     */
    private string $identifier;

    /**
     * The LTI tool which this token was created for.
     */
    private Tool $ltiTool;

    /**
     * The Schoolbox user (if any) this token allows its holder to act in the
     * context of.
     */
    private ?User $user;

    /**
     * When does this token expire?
     */
    private Alaress_Date_Format $expiresAt;

    /**
     * A list of scopes which this token allows access to.
     *
     * @var array<string>
     */
    private array $scopes;

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * @return Tool
     */
    public function getLtiTool(): Tool
    {
        return $this->ltiTool;
    }

    /**
     * @param Tool $ltiTool
     * @return $this
     */
    public function setLtiTool(Tool $ltiTool): self
    {
        $this->ltiTool = $ltiTool;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getExpiresAt(): Alaress_Date_Format
    {
        return $this->expiresAt;
    }

    /**
     * @param Alaress_Date_Format $expiresAt
     * @return $this
     */
    public function setExpiresAt(Alaress_Date_Format $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param string[] $scopes
     * @return $this
     */
    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }
}
