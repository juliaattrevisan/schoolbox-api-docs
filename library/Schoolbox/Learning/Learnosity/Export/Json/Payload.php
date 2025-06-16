<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Json;

use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;

class Payload
{
    /**
     * Create a JSON representation of the quiz $assessment
     *
     * @param QuizDomainObject $assessment
     * @return string
     */
    public function generate(QuizDomainObject $assessment): string
    {
        return json_encode($this->generateArray($assessment));
    }


    /**
     * Create an array representation of the quiz $assessment
     *
     * @param QuizDomainObject $assessment
     * @return array
     */
    private function generateArray(QuizDomainObject $assessment): array
    {
        /* TODO There's probably already a shortcut to this kind of serialization
         *  but I need it in its entirety except for school-specific data like folder,
         *  creator identifiers, etc.
         */
        $struct = [
            'settings' => [
                /* Configuration
                 */
                'title' => $assessment->getTitle(),
                'description' => $assessment->getDescription(),
                'workType' => $assessment->getWorkType()->getName(),
                'dates' => [
                    'publishAt' => $assessment->getPublishAt()->getDatabase(),
                    'openAt' => $assessment->getOpenAt()->getDatabase(),
                    'dueAt' => $assessment->getDueAt()->getDatabase(),
                    'closeAt' => $assessment->getCloseAt()->getDatabase(),
                ],
                'weight' => $assessment->getWeight(),
                'shuffleItems' => $assessment->getShuffleItems(),
                'promptsMode' => $assessment->getPromptsMode(),
                'maxAttempts' => $assessment->getMaxAttempts(),
                'duration' => $assessment->getDuration(),
                'readingTimeDuration' => $assessment->getReadingTimeDuration(),
                'completionMessage' => $assessment->getCompletionMessage(),
                /* Curriculum nodes might not be present in the destination instance, so...
                 * you'll just have to list them by their code and hope for the best?
                 */
                'curriculumNodes' => $assessment->getCurriculumNodes()->indexBy('code')->keys(),
                /* Marking and assessing
                 */
                'markType' => $assessment->getMarkType()->getName(),
                'gradeGroup' => $assessment->getGradeGroup()?->getName(),
                'markScheme' => $assessment->getMarkScheme()->getTemplate(),
                'markVisibility' => $assessment->getMarkVisibility(),
                /* These are school-specific nodes that we probably need to specify later
                 * when re-importing.
                 */
                'creator' => null,
                'context' => null,
                'sequence' => null,
                /* Not supported by quiz, it seems
                 */
                //                'courseManaged' => $assessment->getCourseManaged(),
                //                'sebEnabled' => $assessment->getSebEnabled(),
                //                'defaultOpenSpan' => $assessment->getDefaultOpenSpan(),
            ],
            /* Learnosity stuff goes here
             */
            'content' => []
        ];

        return $struct;
    }
}
