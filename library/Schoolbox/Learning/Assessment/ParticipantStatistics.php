<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use Schoolbox\GenericCollection;

/**
 * An efficient and fast visitor-pattern class to get statistics about a participant.
 *
 * Handles both project and non-project participants.
 */
class ParticipantStatistics
{

    /** @var Participant */
    private $participant;

    /**
     * For simplicity of traversal, flatten the participant's assessment and any sub-assessments
     *
     * @var IAssessable[]|GenericCollection
     */
    private $assessmentAndDescendants;

    /** @var int How many assessments is the participant participating in? */
    private $cacheCountAssessments;

    /** @var int How many assessments does the participant need to respond to? */
    private $cacheCountResponseRequired;

    /** @var int How many assessments is the participant awaiting feedback for? */
    private $cacheCountFeedbackRequired;

    /** @var int How many assessments is the participant reviewed? */
    private $cacheCountReviewComplete;


    /**
     * @param Participant $participant
     */
    public function __construct(Participant $participant)
    {
        $this->participant = $participant;

        // Flatten assessments for simpler traversal
        $assessable                                           = $participant->getPlan()->getAssessable();
        $this->assessmentAndDescendants                       = new GenericCollection(IAssessable::class);
        $this->assessmentAndDescendants[$assessable->getId()] = $assessable;
        if ($assessable instanceof Project) {
            $this->mergeSubAssessments($this->assessmentAndDescendants, $assessable->getAssessables());
        }
    }


    /**
     * If in the unlikely event you mutate any participant's responses
     * or feedbacks, you can clear the cache here.
     */
    public function clearCachedCounts(): void
    {
        $this->cacheCountAssessments = null;
    }


    /**
     * Returns the number of assessments the participant is participating in
     *
     * @return int
     */
    public function getCountAssessments(): int
    {
        if (null === $this->cacheCountAssessments) {
            $this->cacheCountAssessments = 0;
            $user                        = $this->participant->getUser();
            foreach ($this->assessmentAndDescendants as $assessable) {
                if (!$assessable instanceof Project && $assessable->isParticipant($user)) {
                    // Ignore projects and assessments where the user is not participating
                    $this->cacheCountAssessments++;
                }
            }
        }
        return $this->cacheCountAssessments;
    }


    /**
     * @return int
     */
    public function getCountResponseRequired(): int
    {
        if (null === $this->cacheCountResponseRequired) {
            $this->cacheCountResponseRequired = 0;
            $user                             = $this->participant->getUser();
            foreach ($this->assessmentAndDescendants as $assessable) {
                if ($assessable instanceof Project) {
                    continue;
                }
                $subParticipant = $assessable->getParticipantIfExtant($user);
                if ($subParticipant && $subParticipant->isResponseRequired()) {
                    $this->cacheCountResponseRequired++;
                }
            }
        }
        return $this->cacheCountResponseRequired;
    }


    /**
     * @return int
     */
    public function getCountFeedbackRequired(): int
    {
        if (null === $this->cacheCountFeedbackRequired) {
            $this->cacheCountFeedbackRequired = 0;
            $user                             = $this->participant->getUser();
            foreach ($this->assessmentAndDescendants as $assessable) {
                if ($assessable instanceof Project) {
                    continue;
                }
                $subParticipant = $assessable->getParticipantIfExtant($user);
                if ($subParticipant && $subParticipant->isFeedbackRequired()) {
                    $this->cacheCountFeedbackRequired++;
                }
            }
        }
        return $this->cacheCountFeedbackRequired;
    }


    /**
     * @return int
     */
    public function getCountReviewComplete(): int
    {
        if (null === $this->cacheCountReviewComplete) {
            $this->cacheCountReviewComplete = 0;
            $user                           = $this->participant->getUser();
            foreach ($this->assessmentAndDescendants as $assessable) {
                if ($assessable instanceof Project) {
                    continue;
                }
                $subParticipant = $assessable->getParticipantIfExtant($user);
                if ($subParticipant && $subParticipant->isReviewComplete()) {
                    $this->cacheCountReviewComplete++;
                }
            }
        }
        return $this->cacheCountReviewComplete;
    }


    /**
     * Recursively walks the assessment tree and merges them into a flat collection
     *
     * @param IAssessable[]|GenericCollection $merged Indexed by assessment.id
     * @param IAssessable[]|GenericCollection $tree
     */
    private function mergeSubAssessments(GenericCollection $merged, GenericCollection $tree): void
    {
        foreach ($tree as $assessable) {
            $merged[$assessable->getId()] = $assessable;
            if ($assessable instanceof Project) {
                $this->mergeSubAssessments($merged, $assessable->getAssessables());
            }
        }
    }
}
