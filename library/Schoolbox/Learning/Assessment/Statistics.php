<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

/**
 * Observer able to interrogate an assessment and provide statistical information.
 *
 * Caches results so repeated calls to same method do not require re-calculation.
 * You need to call clearCachedCounts() if you mutate any participant's feedbacks or responses.
 */
class Statistics
{

    /**
     * @var IAssessable
     */
    private $assessment;

    // null prior to determination, then int.
    private $cacheCountParticipants;
    private $cacheCountResponseRequired;
    private $cacheCountFeedbackRequired;
    private $cacheCountReviewComplete;
    private $cacheCountHasSubmittedResponse;
    private $cacheCountHasPublishedFeedback;
    private $cacheCountHasMark;


    /**
     * @param IAssessable $assessment
     */
    public function __construct(IAssessable $assessment)
    {
        $this->assessment = $assessment;
    }


    /**
     * If in the unlikely event you mutate any participant's responses
     * or feedbacks, you can clear the cache here.
     */
    public function clearCachedCounts()
    {
        $this->cacheCountParticipants         = null;
        $this->cacheCountResponseRequired     = null;
        $this->cacheCountFeedbackRequired     = null;
        $this->cacheCountReviewComplete       = null;
        $this->cacheCountHasSubmittedResponse = null;
        $this->cacheCountHasPublishedFeedback = null;
        $this->cacheCountHasMark              = null;
    }


    /**
     * Calculate how many Participant instances are in this IAssessable
     *
     * @return int
     */
    public function getCountParticipants(): int
    {
        if (null === $this->cacheCountParticipants) {
            $this->cacheCountParticipants = $this->assessment->getAllParticipants()->count();
        }
        return $this->cacheCountParticipants;
    }


    /**
     * Returns the number of Participants who have no response to the assessment,
     * or their latest response is explicitly marked as 'incomplete'.
     *
     * @return int
     * @throws \DomainException
     */
    public function getCountResponseRequired()
    {
        if (null === $this->cacheCountResponseRequired) {
            $this->cacheCountResponseRequired = 0;
            foreach ($this->assessment->getAllParticipants() as $participant) {
                if ($participant->isResponseRequired()) {
                    $this->cacheCountResponseRequired++;
                }
            }
        }

        return $this->cacheCountResponseRequired;
    }


    /**
     * How many Participants have Responses that require marking.
     *
     * @return int
     * @throws \DomainException
     */
    public function getCountFeedbackRequired()
    {
        if (null === $this->cacheCountFeedbackRequired) {
            $this->cacheCountFeedbackRequired = 0;
            foreach ($this->assessment->getAllParticipants() as $participant) {
                if ($participant->isFeedbackRequired()) {
                    $this->cacheCountFeedbackRequired++;
                }
            }
        }
        return $this->cacheCountFeedbackRequired;
    }


    /**
     * How many participants has the teacher marked as not-explicitly-incomplete, and
     * the student has not subsequently submitted/re-submitted.
     *
     * @return int
     * @throws \DomainException
     */
    public function getCountReviewComplete()
    {
        if (null === $this->cacheCountReviewComplete) {
            $this->cacheCountReviewComplete = 0;
            foreach ($this->assessment->getAllParticipants() as $participant) {
                if ($participant->isReviewComplete()) {
                    $this->cacheCountReviewComplete++;
                }
            }
        }
        return $this->cacheCountReviewComplete;
    }


    /**
     * How many participants have submitted ANY kind of response to this assessment?
     *
     * Includes teacher Submitting-On-Behalf-Of
     *
     * @return int
     */
    public function getCountHasSubmittedResponse()
    {
        if (null === $this->cacheCountHasSubmittedResponse) {
            $this->cacheCountHasSubmittedResponse = 0;
            foreach ($this->assessment->getAllParticipants() as $participant) {
                if ($participant->hasSubmittedResponse()) {
                    $this->cacheCountHasSubmittedResponse++;
                }
            }
        }
        return $this->cacheCountHasSubmittedResponse;
    }


    /**
     * Return the number of participants which have published feedback for this assessment
     *
     * @return int
     */
    public function getCountHasPublishedFeedback()
    {
        if (null === $this->cacheCountHasPublishedFeedback) {
            $this->cacheCountHasPublishedFeedback = 0;
            foreach ($this->assessment->getAllParticipants() as $participant) {
                if ($participant->hasPublishedFeedback()) {
                    $this->cacheCountHasPublishedFeedback++;
                }
            }
        }
        return $this->cacheCountHasPublishedFeedback;
    }


    /**
     * Return the number of participants which have a mark for this assessment
     *
     * This includes both marks given by the teacher, and
     * self-assessed marks for a task (even if self-assessed as 'Incomplete')
     *
     * @return int
     * @throws \DomainException
     */
    public function getCountHasMark()
    {
        if (null === $this->cacheCountHasMark) {
            $this->cacheCountHasMark = 0;
            foreach ($this->assessment->getAllParticipants() as $participant) {
                if ($participant->hasMark()) {
                    $this->cacheCountHasMark++;
                }
            }
        }
        return $this->cacheCountHasMark;
    }


    /**
     * What percentage of Participants still need to submit a response
     *
     * @return float|null
     * @throws \DomainException
     */
    public function getPercentageResponseRequired()
    {
        $countParticipants = $this->getCountParticipants();
        if (!$countParticipants) {
            return null;
        }
        return 100 * ($this->getCountResponseRequired() / $countParticipants);
    }


    /**
     * What percentage of Participants have Responses that require marking.
     *
     * @return float|null
     * @throws \DomainException
     */
    public function getPercentageFeedbackRequired()
    {
        $countParticipants = $this->getCountParticipants();
        if (!$countParticipants) {
            return null;
        }
        return 100 * ($this->getCountFeedbackRequired() / $countParticipants);
    }


    /**
     * What percentage of Participants have been review-completed ?
     *
     * @return float|null
     * @throws \DomainException
     */
    public function getPercentageReviewComplete()
    {
        $countParticipants = $this->getCountParticipants();
        if (!$countParticipants) {
            return null;
        }
        return 100 * ($this->getCountReviewComplete() / $countParticipants);
    }


    /**
     * What percentage of Participants have submitted a response to this assessment ?
     *
     * @return float|null
     * @throws \DomainException
     */
    public function getPercentageHasSubmittedResponse()
    {
        $countParticipants = $this->getCountParticipants();
        if (!$countParticipants) {
            return null;
        }
        return 100 * ($this->getCountHasSubmittedResponse() / $countParticipants);
    }


    /**
     * What percentage of Participants have received published feedback for this assessment ?
     *
     * @return float|null
     * @throws \DomainException
     */
    public function getPercentageHasPublishedFeedback()
    {
        $countParticipants = $this->getCountParticipants();
        if (!$countParticipants) {
            return null;
        }
        return 100 * ($this->getCountHasPublishedFeedback() / $countParticipants);
    }


    /**
     * What percentage of Participants have a mark for this assessment ?
     *
     * @return float|null
     * @throws \DomainException
     */
    public function getPercentageHasMark()
    {
        $countParticipants = $this->getCountParticipants();
        if (!$countParticipants) {
            return null;
        }
        return 100 * ($this->getCountHasMark() / $countParticipants);
    }
}
