<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Json;

use DebugBar\DebugBarException;
use League\Flysystem\FilesystemException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper;

class Payload
{
    /**
     * @param Mapper $storageMapper
     * @param FilesystemProxy $filesystemProxy
     * @param Api $api
     */
    public function __construct(
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private FilesystemProxy $filesystemProxy,
        private Api $api
    ) {
    }


    /**
     * Create a JSON representation of the quiz $assessment
     *
     * @param QuizDomainObject $assessment
     * @return string
     * @throws FilesystemException
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
     * @throws FilesystemException
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
                    'publishAt' => $assessment->getPublishAt()?->getDatabase(),
                    'openAt' => $assessment->getOpenAt()?->getDatabase(),
                    'dueAt' => $assessment->getDueAt()?->getDatabase(),
                    'closeAt' => $assessment->getCloseAt()?->getDatabase(),
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
            'assets' => $this->indexAssets($assessment),
            /* Learnosity stuff goes here
             */
            'content' => $this->fetchLearnosityContent($assessment->getLearnosityReference())
        ];

        return $struct;
    }


    /**
     * Find all the storage info about each asset. Note that an asset can be reused across
     * multiple ckeditors in the quiz such as for Description and Completion Message; we only
     * need to index their storage data once per usage which is why we do it here and not in
     * detectAssets()
     *
     * @param QuizDomainObject $assessment
     * @return array
     * @throws FilesystemException
     */
    private function indexAssets(QuizDomainObject $assessment): array
    {
        $hashes = array_values(array_unique(array_merge(
            $this->detectAssets($assessment->getDescription()),
            $this->detectAssets($assessment->getCompletionMessage()),
        )));

        /* No assets? No trabajo
         */
        if (!\count($hashes)) {
            return [];
        }

        /* Find all the storage instances corresponding to these hashes
         */
        $instances = $this->storageMapper->findByColumn('hash', $hashes);

        $stack = [];

        foreach ($instances as $instance) {
            $index = $instance->getHash();

            /* A string that represents binary data. Hopefully this decodes without corruption.
             * There used to be a thing way back in the early 2000s where people stored binary
             * data in the db (images for image galleries etc) in exactly this kind of manner,
             * but sometimes it...corrupted data. Can't remember the details now, but it used to
             * be a bit of thing
             *
             * Note that fetch.php does a bunch of S3 related stuff that MAYBE needs to be here too
             * I'm...not sure on the particulars
             *
             * TODO find out from mr sannang
             */
            $fileContents = base64_encode($this->filesystemProxy->read($instance));

            $stack[$index] = [
                'basename' => $instance->getBaseName(),
                'contents' => $fileContents
            ];
        }

        return $stack;
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

        return $matches[2];
    }


    /**
     * Grab the learnosity api payloads for $learnosityReference and pile them into one big
     * mega struct...hopefully this works
     *
     * @param string $learnosityReference
     * @return array
     * @throws DebugBarException
     * @throws \JsonException
     * @throws ValidationException
     */
    private function fetchLearnosityContent(string $learnosityReference): array
    {
        /* Get the activity we wanna export
         */
        $activity = $this->api->dataGetRequest('/itembank/activities', ['references' => [$learnosityReference]]);

        /* Get the items in that activity
         */
        $itemReferences = array_column($activity['data'][0]['data']['items'], 'reference');

        $items = $this->api->dataGetRequest('/itembank/items', ['references' => $itemReferences]);

        /* Get the questions in each item
         * TODO Maybe needs chunking
         */
        foreach ($items['data'] as $item) {
            $questionReferences = [];

            /* TODO Maybe needs chunking
             */
            foreach ($item['questions'] as $question) {
                $questionReferences[] =  $question['reference'];
            }

            $question = $this->api->dataGetRequest('/itembank/questions', ['references' => $questionReferences]);

            new \Alaress_Probe($itemReferences, $items, $question);
        }

        return [];
    }
}
