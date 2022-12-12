<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Project\ResultCache;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Mapping;
use Schoolbox\Learning\MarkScheme\LetterGrade;

/**
 * Decides if the project_result cache contains invalid values
 * for the given project $assessment, since changing some of
 * that project's settings causes marks to be calculated in
 * a different way for a different outcome than what we might
 * have previously remembered.
 */
class ValidCache
{
    /**
     * Decide if the $postData makes changes to $assessment in way that
     * invalidates previously stored marks
     *
     * Returns true when IAssessable is not a Project instance
     *
     * @param \Traversable|array $postData
     * @param IAssessable|null   $assessment
     * @return bool|null
     * @throws \InvalidArgumentException
     */
    public function isSatisfiedByProject(\Traversable|array $postData, ?IAssessable $assessment = null): ?bool
    {
        /* $assessment is null?
         * This means $assessment is being created for the first time
         * and might require a project result cache invalidation
         */
        if (null === $assessment) {
            return true;
        }

        /* Not a project instance?
         * Project cache is still valid, no matter what we do here.
         */
        if (!($assessment instanceof Project)) {
            return true;
        }

        /* Decide whether we have to invalidate project_result cache
         * Have to compare $postData and $assessment -before- it's transacted/updated
         *
         * Any one of the following changes invalidates the project result cache since
         * they alter the summation outcome and the normalised- and human-readable mark
         */

        /* 1. Change in Summator
         */
        if ($postData['summator'] !== (new Mapping())->decompose($assessment->getSummator())) {
            return false;
        }

        /* 2. Change in "Require All Activities"
         */
        if ((bool)($postData['requireAllActivities'] ?? false) !== $assessment->getRequireAllActivities()) {
            return false;
        }

        /* 3. Change in Mark Type
         */
        if ((int)$postData['markType'] !== $assessment->getMarkType()->getId()) {
            return false;
        }

        /* 4. If you're using Letter Grade,
         * detect a change in the Grading Scheme
         */
        if ($assessment->getMarkScheme() instanceof LetterGrade) {
            if ((int)$postData['gradingScheme'] !== (int)$assessment->getMarkScheme()->getGradeGroup()->getId()) {
                return false;
            }
        }

        return true;
    }


    /**
     * Decide if the $postData makes changes to $assessment in way that
     * invalidates previously stored marks
     *
     * Returns true when IAssessable is not an activity that exists inside Projects.
     * Activities are: Due Work, Task, Quiz
     *
     * @param \Traversable|array $postData
     * @param IAssessable|null   $assessment
     * @return bool|null
     */
    public function isSatisfiedByActivity(\Traversable|array $postData, ?IAssessable $assessment = null): ?bool
    {
        /* $assessment is null?
         * This means $assessment is being created for the first time
         * and might require a project result cache invalidation
         */
        if (null === $assessment) {
            return false;
        }

        /* Not an Activity instance?
         * (You probably gave this a Project, not an Activity)
         * Project cache is still valid, no matter what we do here.
         */
        if ($assessment instanceof Project) {
            return true;
        }

        /* Decide whether we have to invalidate project_result cache
         * Have to compare $postData and $assessment -before- it's transacted/updated
         */
        $cacheValid = true;

        /* Any one of the following changes invalidates the project result cache since
         * they alter the summation outcome and the normalised- and human-readable mark
         */

        /* 1. Change in Weighting
         */
        if ($postData['weight'] !== $assessment->getWeight()) {
            $cacheValid = false;
        }

        return $cacheValid;
    }
}
