<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Model for OIDC relying parties.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The relying party's name: will be shown when asking the logged-in user
     * whether they wish to share data with the relying party.
     */
    private string $name;

    /**
     * The relying party's client ID: will be generated on creation.
     *
     * @see https://www.oauth.com/oauth2-servers/client-registration/client-id-secret/
     */
    private string $clientId;

    /**
     * The relying party's client secret: will be generated on creation, and
     * then hashed before storing so that its value is not recoverable.
     *
     * Upon creation, the unhashed client secret must be presented to the
     * creator: this is the only time the client secret will be displayed.
     *
     * @see https://www.oauth.com/oauth2-servers/client-registration/client-id-secret/
     */
    private string $clientSecretHash;

    /**
     * The relying party's redirect URI: this is the location to where the
     * user agent will be redirected upon authorization.
     */
    private string $redirectUri;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     * @return $this
     */
    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientSecretHash(): string
    {
        return $this->clientSecretHash;
    }

    /**
     * @param string $clientSecretHash
     * @return $this
     */
    public function setClientSecretHash(string $clientSecretHash): self
    {
        $this->clientSecretHash = $clientSecretHash;
        return $this;
    }

    /**
     * @return string
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * @param string $redirectUri
     * @return $this
     */
    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;
        return $this;
    }
}
