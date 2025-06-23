<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Json;

use League\Flysystem\FilesystemException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper;

class Exporter
{
    /**
     * A switch on widget.widget_type to figure out if this object is a feature or a question
     * specifically applicable to item.definition.widget nodes.
     */
    public const WIDGET_FEATURE = 'feature';


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
        /* Not all curriculum nodes were assigned a uuid which, if it existed, would have been conclusive.
         * Using just the curric code means the destination might have multiple matches since there's no
         * UNIQUE key on curric codes; In this case you won't be able to tell which one of those should be
         * assigned to this quiz. It's inconclusive.
         *
         * Anyway, supply both, using the uuid to match first, otherwise falling back to the curric code,
         * and if that doesn't exist, we can omit this curric code entirely.
         */
        $nodes = $assessment->getCurriculumNodes();

        $curriculumNodes = [];

        foreach ($nodes as $node) {
            $curriculumNodes[] = [
                'code' => $node->getCode(),
                'uuid' => $node->getUuid()
            ];
        }

        /* FIXME There's probably already a shortcut to this kind of serialization
         *  but I need it in its entirety except for school-specific data like folder,
         *  creator identifiers, etc.
         */
        return [
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
                'defaultOpenSpan' => $assessment->getDefaultOpenSpan(),
                /* Curriculum nodes might not be present in the destination instance, so...
                 * you'll just have to list them by their (code, uuid) and hope for the best?
                 */
                'curriculumNodes' => $curriculumNodes,
                /* Marking and assessing
                 */
                'markType' => $assessment->getMarkType()->getSlug(),
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
                // 'courseManaged' => $assessment->getCourseManaged(),
                // 'sebEnabled' => $assessment->getSebEnabled(),

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
            /* Skip video/audio for now. They're too big.
             */
            if ($instance->getMimeType()->isAudio() || $instance->getMimeType()->isVideo()) {
                continue;
            }

            /* Otherwise I guess we try to include it...
             * Nothing stops you embedding a 2gb video into a .jpeg file extension though.
             * hmm
             */
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
     * @throws \JsonException
     * @throws ValidationException
     */
    private function fetchLearnosityContent(string $learnosityReference): array
    {
        /* Get the activity we wanna export
         */
        $activity = $this->api->dataGetRequest('/itembank/activities', ['references' => [$learnosityReference]])['data'][0];

        /* Get the items in that activity
         */
        $itemReferences = array_column($activity['data']['items'], 'reference');

        /* Find all the items in learnosity land
         */
        $itemData = $this->api->dataGetRequest('/itembank/items', ['references' => $itemReferences])['data'];

        /* Get the questions in each item
         */
        $questionReferences = [];

        foreach ($itemData as $item) {
            $questionReferences = array_merge($questionReferences, array_column($item['questions'], 'reference'));
        }

        $questionData = $this->api->dataGetRequest('/itembank/questions', ['references' => $questionReferences])['data'];

        /* Get the features in each item
         */
        $featureReferences = [];

        foreach ($itemData as $item) {
            $featureReferences = array_merge($featureReferences, array_column($item['features'], 'reference'));
        }

        $featureData = $this->api->dataGetRequest('/itembank/features', ['references' => $featureReferences])['data'];

        /* Predetermine new identifiers for items and questions
         */
        $itemReplacementRefs = [];

        foreach ($itemData as $item) {
            $itemReplacementRefs[$item['reference']] = $this->generateGuid();
        }

        $questionReplacementRefs = [];

        foreach ($questionData as $question) {
            $questionReplacementRefs[$question['reference']] = $this->generateGuid();
        }

        $featureReplacementRefs = [];

        foreach ($featureData as $feature) {
            $featureReplacementRefs[$feature['reference']] = $this->generateGuid();
        }

        /* All this stuff contains learnosity references that shouldn't perpetuate beyond this school
         * otherwise you can access data you're not supposed to access. Unfortunately the learnosity
         * scheme is security by obscurity, relying on guessing-the-hash being too difficult to brute
         * force. Revealing them here would negate this "security"
         *
         * Anonymise the references as we further manipulate this stack.
         */

        /* Anonymise feature refs
         */
        $featureStack = [];

        foreach ($featureData as &$feature) {
            $feature['reference'] = $featureReplacementRefs[$feature['reference']];
            $featureStack[$feature['reference']] = $feature;
        }

        unset($feature);

        /* Anonymise question refs
         */
        $questionStack = [];

        foreach ($questionData as &$question) {
            $question['reference'] = $questionReplacementRefs[$question['reference']];
            $questionStack[$question['reference']] = $question;
        }

        unset($question);

        /* Anonymise item refs
         */
        $itemStack = [];

        foreach ($itemData as &$item) {
            /* Constituents of an item is assumed to be either a feature or a question
             */
            foreach ($item['features'] as &$feature) {
                $feature['reference'] = $featureReplacementRefs[$feature['reference']];
            }

            foreach ($item['questions'] as &$question) {
                $question['reference'] = $questionReplacementRefs[$question['reference']];
            }

            /* Rewrite this layout reference business: these identifiers are either
             * features or questions: figure it out based on widget_type being either
             * "feature" for a feature, or "response" for a question (...)
             */
            foreach ($item['definition']['widgets'] as &$widget) {
                if (self::WIDGET_FEATURE === $widget['widget_type']) {
                    $widget['reference'] = $featureReplacementRefs[$widget['reference']];
                } else {
                    $widget['reference'] = $questionReplacementRefs[$widget['reference']];
                }
            }

            /* Update this item's identifier
             */
            $item['reference'] = $itemReplacementRefs[$item['reference']];
            unset($item['tags']);

            /* Push it into the stack
             */
            $itemStack[$item['reference']] = $item;
        }

        unset($item, $widget, $question, $feature);

        /* Anonymise activity refs
         */
        $activity['reference'] = $this->generateGuid();

        unset($activity['tags']);

        foreach ($activity['data']['items'] as &$item) {
            $item['id'] = $itemReplacementRefs[$item['reference']];
            $item['reference'] = $itemReplacementRefs[$item['reference']];
        }

        unset($item);

        /* Don't need these old stacks anymore
         */
        unset($itemData, $questionData, $featureData);

        /* Assign an arbitrary guid here, for a later lookup nicety
         */
        return [
            'activities' => [
                $activity['reference'] => $activity
            ],
            'items' => $itemStack,
            'questions' => $questionStack,
            'features' => $featureStack
        ];
    }


    /**
     * Generate a microsofty style guid
     *
     * @return string
     */
    private function generateGuid(): string
    {
        $uuid = md5(uniqid((string)mt_rand(), true));

        /* Add an arbitrary prefix to the start of this string to make it clear that
         * it's one of our own making, not belonging to learnosity at all
         */
        return sprintf(
            '%s-%s-%s-%s-%s-%s',
            'sb',
            substr($uuid, 0, 8),
            substr($uuid, 8, 4),
            substr($uuid, 12, 4),
            substr($uuid, 16, 4),
            substr($uuid, 20, 12)
        );
    }
}
