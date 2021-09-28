<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment;

use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\User;

/**
 * Domain model for LTI assessments.
 */
class Model extends AbstractAssessment
{
    private const FAMILY_NAME       = 'ltiAssessment';
    private const DEFAULT_TYPE_NAME = 'LTI Assessment';

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
}
