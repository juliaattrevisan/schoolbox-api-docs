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
            $dehydrated['dueWork'] = [
                'acceptOnline'      => $assessment->getAcceptOnline(),
                'selfReviewability' => $assessment->getSelfReviewability(),
            ];
        }

        // Quiz-specific
        // TODO figure out what's actually needed here
        if ($assessment instanceof QuizModel) {
            $dehydrated['quiz'] = [
                'duration'    => $assessment->getDuration(),
                'maxAttempts' => $assessment->getMaxAttempts(),
            ];
        }

        // Task-specific
        if ($assessment instanceof TaskModel) {
            $dehydrated['task'] = [
                'selfAssessable' => $assessment->getIsSelfAssessable(),
            ];
        }

        // Project-specific
        if ($assessment instanceof Project) {
            $activities = [];
            foreach ($assessment->getAssessables() as $activity) {
                $activities[$activity->getId()] = $this->dehydrateForDiff($activity);
            }
            $dehydrated['project'] = [
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
}
