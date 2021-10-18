<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Closure;

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
     * @param int  $minimumVisibility
     */
    protected function recombine(
        $includeUnpublishedSelfReflectionFeedback,
        $includeUnpublishedInstructorFeedback,
        $includeDraftResponses,
        $minimumVisibility
    ) {
        $this->recombined = $this->recombined
            ->merge(
                $includeDraftResponses
                    ? $this->participant->getSubmittedResponses()
                    : $this->participant->getAllResponses()
            )
            ->merge(
                $includeUnpublishedInstructorFeedback
                    ? $this->participant->getPublishedFeedbacks()
                    : $this->participant->getAllFeedbacks()
            )
            ->sortByCallback(Closure::fromCallable([$this, 'sortRecombined']));
    }
}
