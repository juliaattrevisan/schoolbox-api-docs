<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\LTI\Provider\Model as Tool;
use Schoolbox\User;

/**
 * Domain model for LTI assessments.
 */
class Model extends AbstractAssessment
{
    private const FAMILY_NAME       = 'ltiAssessment';
    private const DEFAULT_TYPE_NAME = '3rd Party Assessment';

    /**
     * The LTI assessment's URL, if there is one.
     *
     * This value will be fetched from the "url" field of an LTI Resource Link
     * content item returned as part of a deep linking response message
     * (or the tool's link launch URL, if not present):
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#lti-resource-link
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#deep-linking-response-message
     *
     * An LTI resource link launch request will be created for this URL when the
     * student attempts the assessment:
     * @see https://www.imsglobal.org/spec/lti/v1p3/#resource-link-launch-request-message
     */
    private ?string $ltiAssessmentUrl = null;

    /**
     * The title of the LTI assessment, as retrieved from the LTI tool.
     *
     * This value will be fetched from the "title" field of an LTI Resource Link
     * content item returned as part of a deep linking response message:
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#lti-resource-link
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#deep-linking-response-message
     *
     * This is distinct from the base `title` field in that:
     * * this is currently only used when displaying a card for the resource
     *   link launch request
     */
    private ?string $ltiAssessmentTitle = null;

    /**
     * The text of the LTI assessment, as retrieved from the LTI tool.
     *
     * This value will be fetched from the "text" field of an LTI Resource Link
     * content item returned as part of a deep linking response message:
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#lti-resource-link
     * @see https://www.imsglobal.org/spec/lti-dl/v2p0#deep-linking-response-message
     *
     * This is distinct from the base `description` field in that:
     * * this is currently only used when displaying a card for the resource
     *   link launch request
     * * this is plain text, not HTML
     * * this is not subject to Custom Assessment Participation
     */
    private ?string $ltiAssessmentText = null;

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
     *
     * These fields may be subject to parameter substitution:
     * @see https://www.imsglobal.org/spec/lti/v1p3/#customproperty
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
     * Standard rules for whether a learner may create a response:
     * they must be a participant, and the assessment must be open.
     *
     * @param User $learner
     * @return bool
     */
    public function canLearnerCreateResponse(User $learner)
    {
        return $this->isParticipant($learner) && $this->isOpen($learner);
    }

    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return self::FAMILY_NAME;
    }

    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return self::DEFAULT_TYPE_NAME;
    }

    /**
     * @inheritdoc
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly)
    {
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);
        return $feedback ? $feedback->getNormalisedMark() : null;
    }

    /**
     * @inheritdoc
     */
    public function getHumanReadableMark(Participant $participant, $publishedOnly)
    {
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);
        return $feedback ? $feedback->getHumanReadableMark() : null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly)
    {
        $this->assertSameAssessment($participant);
        return $publishedOnly
            ? $participant->findMostRecentSubmittedResponse()
            : $participant->findMostRecentResponse();
    }

    /**
     * @inheritdoc
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly)
    {
        $this->assertSameAssessment($participant);
        return $publishedOnly
            ? $participant->findMostRecentInstructorGivenPublishedFeedback()
            : $participant->findMostRecentInstructorGivenFeedback();
    }

    /**
     * Overwrite fields from `$source` into this object.
     *
     * This implementation overwrites the additional fields over and above the
     * base implementation:
     * * custom fields, line item resource ID, score maximum:
     *   possibly contentious, because these fields relate to a particular item
     *   within an LTI tool. Should the overwritten item relate to that LTI item
     *   as well?
     * * LTI assessment text, title, URL, tool
     *
     * @param self $source
     * @return $this
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);
        $this->customFields         = $source->customFields;
        $this->lineItemResourceId   = $source->lineItemResourceId;
        $this->lineItemScoreMaximum = $source->lineItemScoreMaximum;
        $this->ltiAssessmentText    = $source->ltiAssessmentText;
        $this->ltiAssessmentTitle   = $source->ltiAssessmentTitle;
        $this->ltiAssessmentUrl     = $source->ltiAssessmentUrl;
        $this->ltiTool              = $source->ltiTool;
        return $this;
    }


    /**
     * Feedback is required on this assessment if there is a response, and there
     * is no feedback which is more recent than that response.
     *
     * @param Participant $participant
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        $response = $this->getAuthoritativeResponse($participant, true);
        if (!$response) {
            return false;
        }

        $feedback = $this->getAuthoritativeFeedback($participant, true);
        if (!$feedback) {
            return true;
        }

        return $response->getSubmittedAt() > $feedback->getPublishedAt();
    }

    /**
     * LTI assessments don't have draft marks.
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        $this->assertSameAssessment($participant);
        return false;
    }

    /**
     * @inheritdoc
     */
    public function assertValidDates()
    {
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'open'    => $this->getOpenAt(),
            'due'     => $this->getDueAt(),
            'late'    => $this->getCloseAt(),
        ]);
    }

    /**
     * @return string|null
     */
    public function getLtiAssessmentUrl(): ?string
    {
        return $this->ltiAssessmentUrl;
    }

    /**
     * @param string|null $ltiAssessmentUrl
     * @return static
     */
    public function setLtiAssessmentUrl(?string $ltiAssessmentUrl): self
    {
        $this->ltiAssessmentUrl = $ltiAssessmentUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLtiAssessmentTitle(): ?string
    {
        return $this->ltiAssessmentTitle;
    }

    /**
     * @param string|null $ltiAssessmentTitle
     * @return static
     */
    public function setLtiAssessmentTitle(?string $ltiAssessmentTitle): self
    {
        $this->ltiAssessmentTitle = $ltiAssessmentTitle;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLtiAssessmentText(): ?string
    {
        return $this->ltiAssessmentText;
    }

    /**
     * @param string|null $ltiAssessmentText
     * @return static
     */
    public function setLtiAssessmentText(?string $ltiAssessmentText): self
    {
        $this->ltiAssessmentText = $ltiAssessmentText;
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
     * @return static
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
     * @return static
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
     * @return static
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
     * @return static
     */
    public function setLineItemScoreMaximum(?float $lineItemScoreMaximum): self
    {
        $this->lineItemScoreMaximum = $lineItemScoreMaximum;
        return $this;
    }
}
