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
 * Dehydrator for IAssessable objects.
 */
class Dehydrator
{
    /**
     * Dehydrates assessment, for purpose of generating a diff.
     *
     * Expected general usage is to call this on `$assessment` before and
     * after it gets saved, and then use that diff in a notifier.
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function dehydrate(IAssessable $assessment): array
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
            'participants'   => [],
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
                $activities[$activity->getId()] = $this->dehydrate($activity);
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
                'user'          => [
                    'id'        => $user->getId(),
                    'title'     => $user->getTitle(),
                    'firstname' => $user->getFirstname(),
                    'lastname'  => $user->getLastname(),
                ]
            ];
        }

        return $dehydrated;
    }
}
