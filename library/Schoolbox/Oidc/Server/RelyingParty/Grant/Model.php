<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\Grant;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Oidc\Server\RelyingParty\Model as RelyingParty;
use Schoolbox\User;

/**
 * Model representation of the permission a user has granted to an OIDC relying
 * party.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Default expiry time for a grant is 1 year.
     */
    public const DEFAULT_EXPIRY = '+1 year';

    /**
     * The relying party to which the user granted access.
     */
    private RelyingParty $relyingParty;

    /**
     * The user which granted access to the relying party.
     */
    private User $user;

    /**
     * When does the access expire?
     */
    private Alaress_Date_Format $expiresAt;

    /**
     * The list of scopes this user has granted access to.
     *
     * @var GenericCollection<Scope\Model>
     */
    private GenericCollection $scopes;

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
     * Has this permission grant expired as of `$now`?
     *
     * @param Alaress_Date_Format $now
     * @return bool
     */
    public function isExpired(Alaress_Date_Format $now): bool
    {
        return $this->expiresAt->lt($now);
    }

    /**
     * @return RelyingParty
     */
    public function getRelyingParty(): RelyingParty
    {
        return $this->relyingParty;
    }

    /**
     * @param RelyingParty $relyingParty
     * @return $this
     */
    public function setRelyingParty(RelyingParty $relyingParty): self
    {
        $this->relyingParty = $relyingParty;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return GenericCollection<Scope\Model>
     */
    public function getScopes(): GenericCollection
    {
        return $this->scopes;
    }

    /**
     * @param GenericCollection<Scope\Model> $scopes
     * @return $this
     */
    public function setScopes(GenericCollection $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }
}
