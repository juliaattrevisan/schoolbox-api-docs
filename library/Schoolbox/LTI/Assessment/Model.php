<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\LTI\Provider\Model as Tool;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * LTI assessment model.
 */
class Model implements IAssessableDataModel, ISoftDeletable
{
    use Id;
    use AssessmentModelTrait;

    /**
     * The URL of the LTI assessment,
     *
     * This value will be fetched from an LTI Resource Link content item
     * returned as part of a deep linking response message:
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#lti-resource-link
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#deep-linking-response-message
     *
     * An LTI resource link launch request will be created for this URL when the
     * student attempts the assessment:
     * @see https://www.imsglobal.org/spec/lti/v1p3/#resource-link-launch-request-message
     */
    private ?string $ltiAssessmentUrl = null;

    /**
     * The LTI tool with which the LTI assessment URL was selected.
     *
     * An LTI resource link launch request must be associated with an LTI tool
     * registration in order for the launch to be instigated.
     */
    private ?Tool $ltiTool = null;

    /**
     * Any custom fields which must be passed as part of the LTI resource link
     * launch request.
     */
    private ?array $customFields = null;

    /**
     * The line item resource ID (if any): assessments passed from the LTI tool
     * should contain a line item, otherwise Schoolbox won't be able to
     * recognise that they are assessments.
     *
     * AFAICT this will be used to find which LTI assessments will have scores
     * updated, when the LTI tool posts scores back to Schoolbox:
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0/#score-publish-service
     */
    private ?string $lineItemResourceId = null;

    /**
     * The maximum score for the line item (if any).
     */
    private ?float $lineItemScoreMaximum = null;

    /**
     * @return string|null
     */
    public function getLtiAssessmentUrl(): ?string
    {
        return $this->ltiAssessmentUrl;
    }

    /**
     * @param string|null $ltiAssessmentUrl
     */
    public function setLtiAssessmentUrl(?string $ltiAssessmentUrl): void
    {
        $this->ltiAssessmentUrl = $ltiAssessmentUrl;
    }

    /**
     * @return Tool|null
     */
    public function getLtiTool(): ?Tool
    {
        return $this->ltiTool;
    }

    /**
     * @param Tool|null $ltiTool
     * @return $this
     */
    public function setLtiTool(?Tool $ltiTool): self
    {
        $this->ltiTool = $ltiTool;
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

    /**
     * The LTI assessment takes its deleted status from the assessment which it
     * is composed of.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->assessmentModel->isDeleted();
    }
}
