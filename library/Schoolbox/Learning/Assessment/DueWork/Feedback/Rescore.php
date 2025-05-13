<?php

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Learning\Assessment\IAssessable;

class Rescore
{
    /**
     * Takes a fully hydrated $assessment via findByIdForReview()) and recalculates
     * normalised and human readable marks using $assessment's current settings for
     * Mark Type
     *
     * This is used to change the feedbacks' mark type after they've been marked.
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function generate(IAssessable $assessment): array
    {
        $changes = [];

        foreach ($assessment->getAllParticipants() as $participant) {
            $authoritativeFeedback = $participant->getAuthoritativeFeedback(true);

            if ($authoritativeFeedback) {
                /* Recalibrate normalised mark from the human readable mark, as if you'd done that on the ui
                 * This prevents you having a normalised mark that would otherwise have been unattainable,
                 * such as a value between the lower and upper bounds of Letter Grades.
                 */
                $newStudentMark = $assessment->getMarkScheme()->getHumanReadableMark($authoritativeFeedback->getNormalisedMark());
                $newInstructorMark = $assessment->getInstructorMarkScheme()->getHumanReadableMark($authoritativeFeedback->getNormalisedMark());
                $newNormalisedMark = $assessment->getInstructorMarkScheme()->getNormalisedMark($newInstructorMark);

                $changes[] = [
                    'user' => $participant->getUser(),
                    'old' => [
                        'normalised' => $authoritativeFeedback->getNormalisedMark(),
                        'student' => $authoritativeFeedback->getHumanReadableMark(),
                        'instructor' => $authoritativeFeedback->getHumanReadableInstructorMark(),
                    ] ,
                    'new' => [
                        'normalised' => $newNormalisedMark,
                        'student' => $newStudentMark,
                        'instructor' => $newInstructorMark
                    ]
                ];
            }
        }

        return $changes;
    }
}