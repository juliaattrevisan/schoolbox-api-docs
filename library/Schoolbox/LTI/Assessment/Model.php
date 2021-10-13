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
     */
    private ?string $ltiAssessmentUrl = null;

    /**
     * The LTI tool with which the LTI assessment URL was selected.
     */
    private ?Tool $ltiTool = null;

    /**
     * Any custom fields which must be passed as part of the LTI resource link
     * launch request.
     */
    private ?array $customFields = null;

    /**
     * The line item resource ID (if any).
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
     * @return $this
     */
    public function setLtiAssessmentUrl(?string $ltiAssessmentUrl): self
    {
        $this->ltiAssessmentUrl = $ltiAssessmentUrl;
        return $this;
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
