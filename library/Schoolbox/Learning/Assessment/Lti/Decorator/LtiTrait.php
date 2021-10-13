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
     * The LTI tool ID
     */
    protected ?int $ltiToolId = null;

    /**
     * Any custom fields which must be passed when linking to the assessment URL
     */
    protected ?array $customFields = null;

    /**
     * The line item resource ID (if any).
     */
    protected ?string $lineItemResourceId = null;

    /**
     * The maximum score for the line item (if any).
     */
    protected ?float $lineItemScoreMaximum = null;

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

    /**
     * @return int|null
     */
    public function getLtiToolId(): ?int
    {
        return $this->ltiToolId;
    }

    /**
     * @param int|null $ltiToolId
     * @return $this
     */
    public function setLtiToolId(?int $ltiToolId): self
    {
        $this->ltiToolId = $ltiToolId;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    /**
     * @param array|null $customFields
     * @return $this
     */
    public function setCustomFields(?array $customFields): self
    {
        $this->customFields = $customFields;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLineItemResourceId(): ?string
    {
        return $this->lineItemResourceId;
    }

    /**
     * @param string|null $lineItemResourceId
     * @return $this
     */
    public function setLineItemResourceId(?string $lineItemResourceId): self
    {
        $this->lineItemResourceId = $lineItemResourceId;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getLineItemScoreMaximum(): ?float
    {
        return $this->lineItemScoreMaximum;
    }

    /**
     * @param float|null $lineItemScoreMaximum
     * @return $this
     */
    public function setLineItemScoreMaximum(?float $lineItemScoreMaximum): self
    {
        $this->lineItemScoreMaximum = $lineItemScoreMaximum;
        return $this;
    }
}
