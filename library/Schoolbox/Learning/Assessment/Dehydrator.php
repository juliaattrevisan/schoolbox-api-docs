<?php
declare(strict_types=1);

/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizModel;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;

/**
 * Strategy for sorting assessments.
 *
 * This orders them by their due date (either base or participant's),
 * then by their sequence, then by their title.
 *
 * Usage: `$assessmentCollection->sortByCallback(new DueDateSortStrategy($student));`
 */
class Dehydrator
{
    /**
     * Dehydrates assessment, for purpose of generating a diff.
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function dehydrateForDiff(IAssessable $assessment): array
    {
        // This, or something else, needs to know how to dehydrate each IAssessable type.
        $dehydrated = [
            'id'             => $assessment->getId(),
            'type'           => $assessment->getFamilyName(),
            'title'          => $assessment->getTitle(),
            'weight'         => $assessment->getWeight(),
            'markVisibility' => $assessment->getMarkVisibility(),
            'workType'       => $assessment->getWorkType()
                ? [
                    'id'   => $assessment->getWorkType()->getId(),
                    'name' => $assessment->getWorkType()->getName(),
                ]
                : null,
        ];

        // Due-work specific
        // TODO would be more polite to put in its own helper, but ram it in here for now
        if ($assessment instanceof DueWorkModel) {
            $dehydrated += [
                'acceptOnline'      => $assessment->getAcceptOnline(),
                'selfReviewability' => $assessment->getSelfReviewability(),
            ];
        }

        // Quiz-specific
        // TODO figure out what's actually needed here
        if ($assessment instanceof QuizModel) {
            $dehydrated += [
                'duration'    => $assessment->getDuration(),
                'maxAttempts' => $assessment->getMaxAttempts(),
            ];
        }

        // Task-specific
        if ($assessment instanceof TaskModel) {
            $dehydrated += [
                'selfAssessable' => $assessment->getIsSelfAssessable(),
            ];
        }

        // Project-specific
        if ($assessment instanceof Project) {
            $activities = [];
            foreach ($assessment->getAssessables() as $activity) {
                $activities[$activity->getId()] = $this->dehydrateForDiff($activity);
            }
            $dehydrated += [
                'activities' => $activities,
            ];
        }

        // Dehydrate participants too
        foreach ($assessment->getAllParticipants() as $participant) {
            $user = $participant->getUser();
            $plan = $participant->getPlan();
            $dehydrated['participants'][$participant->getUser()->getId()] = [
                'description' => $participant->getDescription(),
                'publishDate' => $participant->getPublishDate()->getDatabase(),
                'openDate'    => $participant->getOpenDate()->getDatabase(),
                'dueDate'     => $participant->getDueDate()->getDatabase(),
                'closeDate'   => $participant->getCloseDate()->getDatabase(),
                'excluded'    => $participant->isExcluded(),
                'plan'        => [
                    'id'      => $plan->getId(),
                    'name'    => $plan->getName(),
                    'dynamic' => $plan->getIsDynamic(),
                ],
                'user'        => [
                    'id'      => $user->getId(),
                    'name'    => $user->formatName(),
                ]
            ];;
        }

        return $dehydrated;
    }

    /**
     * Generates a diff that is (somewhat) compatible with mapper diffs.
     *
     * This has to be done recursively
     *
     * @param array $before
     * @param array $after
     * @return array
     * @throws \InvalidArgumentException if somewhere in the process an array
     *                                   gets diffed with a non-null scalar
     */
    public function diff(array $before, array $after): array
    {
        return $this->diffInternal($before, $after, '');
    }

    /**
     * Internal diff generation: it tracks the path that is being diffed.
     *
     * @param array  $before
     * @param array  $after
     * @param string $path
     * @return array
     * @throws \InvalidArgumentException if somewhere in the process an array
     *                                   gets diffed with a non-null scalar
     */
    private function diffInternal(array $before, array $after, string $path): array
    {
        $diff = [];

        // Ensure all the same keys exist in $before and $after
        foreach (array_diff_key($before, $after) as $key => $_) {
            $after[$key] = \is_array($before[$key]) ? [] : null;
        }
        foreach (array_diff_key($after, $before) as $key => $_) {
            $before[$key] = \is_array($after[$key]) ? [] : null;
        }

        // We can assume all the same keys exist in $before and $after
        // (except project activities, but those are recursed)
        foreach ($before as $key => $beforeValue) {
            $afterValue = $after[$key];
            $thisPath   = $path . $key;

            // Both beforeValue and afterValue must be arrays, or neither may be.
            // If one is and another isn't: that's an error
            $isBeforeArray = \is_array($beforeValue);
            $isAfterArray  = \is_array($afterValue);
            if ($isBeforeArray xor $isAfterArray) {
                throw new \InvalidArgumentException(
                    sprintf('Cannot diff array with a non-array in key "%s"', $thisPath)
                );
            }

            // Is this scalar? Diff it now, otherwise recurse
            if (\is_array($beforeValue) && \is_array($afterValue)) {
                $recursedDiff = $this->diffInternal($beforeValue, $afterValue, $thisPath . '.');
                if (!empty($recursedDiff)) {
                    $diff[$key] = $recursedDiff;
                }
            } elseif ($beforeValue !== $afterValue) {
                $diff[$key] = [$beforeValue, $afterValue];
            }
        }
        return $diff;
    }
}
