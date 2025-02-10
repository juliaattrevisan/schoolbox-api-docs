<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\Grant\Scope;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Oidc\Server\RelyingParty\Grant\Model as RelyingPartyGrant;

/**
 * Model representation of the scopes a user has granted access to, as part of
 * granting access to a relying party.
 */
class Model
{
    use Id;

    /**
     * The grant this scope belongs to.
     */
    private RelyingPartyGrant $relyingPartyGrant;

    /**
     * The scope which was granted.
     */
    private string $scope;

    /**
     * @return RelyingPartyGrant
     */
    public function getRelyingPartyGrant(): RelyingPartyGrant
    {
        return $this->relyingPartyGrant;
    }

    /**
     * @param RelyingPartyGrant $relyingPartyGrant
     * @return $this
     */
    public function setRelyingPartyGrant(RelyingPartyGrant $relyingPartyGrant): self
    {
        $this->relyingPartyGrant = $relyingPartyGrant;
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
