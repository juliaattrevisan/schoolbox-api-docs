<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Timeline;

use Closure;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Lti;
use Schoolbox\Learning\Assessment\LessonPlan;
use Schoolbox\Learning\Assessment\Quiz;

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

        $markVisibility = $this->participant->getAssessable()->getMarkVisibility();

        if ($markVisibility === null) {
            // Use the default value from the mark_visibility field of the relevant database table.
            $assessment = $this->participant->getAssessable();
            if ($assessment instanceof Project) {
                // The default value of database column project.mark_visibility is 'staffAndStudent'.
                $markVisibility = IAssessable::MARK_VISIBILITY_STUDENT;
            } elseif ($assessment instanceof DueWork\Model) {
                // The default value of database column submission_box.mark_visibility is 'staffOnly'.
                $markVisibility = IAssessable::MARK_VISIBILITY_PRIVATE;
            } elseif ($assessment instanceof Task\Model) {
                // Table `task` has no mark_visibility column, which makes sense because
                // Task\Model->getMarkVisibility() always returns MARK_VISIBILITY_PARENT_STUDENT.
                // So this case should never execute.  Handle it anyway for completeness.
                $markVisibility = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
            } elseif ($assessment instanceof Quiz\Model) {
                // The default value of database column assess.mark_visibility is 'staffAndStudent'.
                $markVisibility = IAssessable::MARK_VISIBILITY_STUDENT;
            } elseif ($assessment instanceof Lti\Model) {
                // The default value of database column lti_assessment.mark_visibility is null.  Assume 'staffOnly'.
                $markVisibility = IAssessable::MARK_VISIBILITY_PRIVATE;
            } elseif ($assessment instanceof LessonPlan\Model) {
                // The default value of database column lesson_plan.visibility is undefined.  Assume 'staffOnly'.
                $markVisibility = IAssessable::MARK_VISIBILITY_PRIVATE;
            } else {
                // This should never execute, but handle it for completeness.
                $markVisibility = IAssessable::MARK_VISIBILITY_PRIVATE;
            }
        }

        $isFeedbackVisible = in_array($markVisibility, $allowedVisibilities, true);

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
