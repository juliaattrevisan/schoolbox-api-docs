<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\User\Scope;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Oidc\Server\RelyingParty\User\Model as RelyingPartyUser;

/**
 * Model representation of the scopes a user has granted access to, as part of
 * granting access to a relying party.
 */
class Model
{
    use Id;

    /**
     * The relying party + user this scope belongs to.
     */
    private RelyingPartyUser $relyingPartyUser;

    /**
     * The scope which was granted.
     */
    private string $scope;

    /**
     * @return RelyingPartyUser
     */
    public function getRelyingPartyUser(): RelyingPartyUser
    {
        return $this->relyingPartyUser;
    }

    /**
     * @param RelyingPartyUser $relyingPartyUser
     * @return $this
     */
    public function setRelyingPartyUser(RelyingPartyUser $relyingPartyUser): self
    {
        $this->relyingPartyUser = $relyingPartyUser;
        return $this;
    }

    /**
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @param string $scope
     * @return $this
     */
    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }
}
