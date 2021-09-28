<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Decorator;

trait LtiTrait
{
    /**
     * The assessment URL
     */
    protected ?string $ltiAssessmentUrl = null;

    /**
     * @return string|null
     */
    public function getLtiAssessmentUrl(): ?string
    {
        return $this->ltiAssessmentUrl;
    }

    /**
     * @param string|null $ltiAssessmentUrl
     * @return $this
     */
    public function setLtiAssessmentUrl(?string $ltiAssessmentUrl): self
    {
        $this->ltiAssessmentUrl = $ltiAssessmentUrl;
        return $this;
    }
}
