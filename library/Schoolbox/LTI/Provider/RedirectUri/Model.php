<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\LTI\Provider\RedirectUri;

use Schoolbox\LTI\Provider as LtiProvider;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * A valid redirect URI for an LTI provider (a.k.a. tool).
 */
class Model
{
    use Id;

    /**
     * The LTI provider to which this redirect URI is associated.
     */
    private LtiProvider\Model $ltiProvider;

    /**
     * The actual redirect URI which Schoolbox is allowed to redirect to, when
     * it receives a launch request from the provider.
     */
    private string $redirectUri;

    /**
     * @return LtiProvider\Model
     */
    public function getLtiProvider(): LtiProvider\Model
    {
        return $this->ltiProvider;
    }

    /**
     * @param LtiProvider\Model $ltiProvider
     * @return $this
     */
    public function setLtiProvider(LtiProvider\Model $ltiProvider): self
    {
        $this->ltiProvider = $ltiProvider;
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
