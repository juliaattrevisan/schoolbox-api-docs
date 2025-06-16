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
            /* These are locally-grown storage models that were used somewhere in this quiz.
             * When importing into your own school, you'll already have them: but, importing
             * into a different school means you probably won't have them, and need them shoved
             * into the destination's storage. I guess we make a zip file for this, now?
             *
             * There are a few options available:
             *  - Do nothing. Don't support assets of any kind. This is brutal for users, probably,
             *    but cheap for us.
             *  - Have a base64 representation somewhere in the xml that we can just shove into
             *    the destination's storage. We already have an InlineConverter which probably
             *    services this use case, but we'd have to apply it somehow. This bloats the resultant
             *    json file substantially, especially for large media like audio/video/high-res images
             *  - Zip file containing json text file + any associated assets. But then you have to
             *    deal with taskd, zip files, download limits, all this hankypanky
             */
            'assets' => array_values(array_unique(array_merge(
                $this->detectAssets($assessment->getDescription()),
                $this->detectAssets($assessment->getCompletionMessage()),
            ))),
            /* Learnosity stuff goes here
             */
            'content' => []
        ];


        return $struct;
    }


    /**
     * In the text slab $input, find all the references to storage accesses
     * and make a list of their hashes
     *
     * @param string $input
     * @return array
     */
    private function detectAssets(string $input): array
    {
        /* Accommodate both relative and absolute urls
         */
        preg_match_all('/\/storage\/(image|fetch)\.php\?hash=([^"]+)/', $input, $matches);

        return $matches[2] ?? [];
    }
}
