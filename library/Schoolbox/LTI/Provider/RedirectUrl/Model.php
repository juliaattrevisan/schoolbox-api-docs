<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\LTI\Provider\RedirectUrl;

use Schoolbox\LTI\Provider as LtiProvider;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * A valid redirect URL for an LTI provider (a.k.a. tool).
 */
class Model
{
    use Id;

    /**
     * The LTI provider to which this redirect URL is associated.
     */
    private LtiProvider\Model $ltiProvider;

    /**
     * The actual redirect URL which Schoolbox is allowed to redirect to, when
     * it receives a launch request from the provider.
     */
    private string $redirectUrl;

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
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    /**
     * @param string $redirectUrl
     * @return $this
     */
    public function setRedirectUrl(string $redirectUrl): self
    {
        $this->redirectUrl = $redirectUrl;
        return $this;
    }
}
