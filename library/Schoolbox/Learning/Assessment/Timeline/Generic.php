<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Closure;
use Schoolbox\Collection;

/**
 * Generic timeline builder.
 *
 * Unlike the base builder (which assumes it's working with due work responses
 * and feedback), this makes no assumptions about what the feedback or response
 * objects are composed of.
 */
class Generic extends Full
{
    /**
     * Combine the responses and feedback into a single collection.
     *
     * Since this timeline works with any sort of response and feedback:
     * * `$includeUnpublishedSelfReflectionFeedback` is ignored, as the concept
     *   of self-reflection exists only for due work
     * * `$minimumVisibility` is ignored, as the concept of mark visibility
     *   exists only for due work and quizzes
     *
     * @param bool $includeUnpublishedSelfReflectionFeedback
     * @param bool $includeUnpublishedInstructorFeedback
     * @param bool $includeDraftResponses
     * @param array $allowedVisibilities
     */
    protected function recombine(
        $includeUnpublishedSelfReflectionFeedback,
        $includeUnpublishedInstructorFeedback,
        $includeDraftResponses,
        $allowedVisibilities
    ) {
        $responses = $includeDraftResponses
            ? $this->participant->getSubmittedResponses()
            : $this->participant->getAllResponses();

        // Can feedbacks be seen due to minumum visibility?
        // If the assessment doesn't support mark visibility, then assume that
        // feedbacks are always visible.
        $isFeedbackVisible = true;
        if ($isFeedbackVisible) {
            $feedbacks = $includeUnpublishedInstructorFeedback
                ? $this->participant->getPublishedFeedbacks()
                : $this->participant->getAllFeedbacks();
        } else {
            $feedbacks = new Collection();
        }
        $this->recombined = $this->recombined
            ->merge($feedbacks)
            ->merge($responses)
            ->sortByCallback(Closure::fromCallable([$this, 'sortRecombined']));
    }
}
