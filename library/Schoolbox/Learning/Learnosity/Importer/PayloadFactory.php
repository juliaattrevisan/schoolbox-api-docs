<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use Ramsey\Uuid\Uuid;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Database\Strategy\Factory as LegacyDbStrategyFactory;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy\Factory as LegacyXmlStrategyFactory;

/**
 * Spawns a Learnosity-friendly payload structure for Activities,
 * Items, etc. based on the data provided.
 */
class PayloadFactory
{
    /**
     * Arbitrary consts used in a match() clause
     */
    public const LEARNOSITY_PAYLOAD_ACTIVITY = 'activity';
    public const LEARNOSITY_PAYLOAD_ITEM = 'item';

    public const LEARNOSITY_PAYLOAD_SECTION_FEATURE = 'sectionFeature';
    public const LEARNOSITY_PAYLOAD_QUESTION = 'question';


    /**
     * A somewhat undocumented limit that, if exceeded, results in http 500 and error code 22001 (undocumented)
     * https://help.learnosity.com/hc/en-us/requests/23332
     *
     * This is the only reference such a limit exists, but it's not in the Data API or Items API docs
     * and it doesn't mention anything about error 22001...
     * https://help.learnosity.com/hc/en-us/articles/360000754998-Enabling-and-Configuring-Item-Titles-in-the-Author-API
     */
    private const LEARNOSITY_MAX_TITLE_LENGTH = 150;


    /**
     * Constructor.
     *
     * @param LegacyDbStrategyFactory|LegacyXmlStrategyFactory $strategyFactory
     */
    public function __construct(
        private LegacyDbStrategyFactory|LegacyXmlStrategyFactory $strategyFactory
    ) {
    }


    /**
     * Spawns a Learnosity-friendly payload structure for Activities,
     * Items, etc. based on the data provided.
     *
     * @param string $type
     * @param array $data
     * @param array|null $tags
     * @return array
     * @throws \DomainException
     */
    public function produce(string $type, array $data, ?array $tags): array
    {
        return match ($type) {
            self::LEARNOSITY_PAYLOAD_ACTIVITY => $this->setupActivityNode($data, $tags),
            self::LEARNOSITY_PAYLOAD_ITEM => $this->setupItemNode($data, $tags),
            self::LEARNOSITY_PAYLOAD_SECTION_FEATURE => $this->setupSectionFeatureNode($data, $tags),
            self::LEARNOSITY_PAYLOAD_QUESTION => $this->setupQuestionNode($data),
            default => [],
        };
    }


    /**
     * Learnosity has an undocumented 150-char limit resulting in error 22001 if exceeded
     * There's no real convenient place for this, but this method takes an arbitrary $title
     * string and:
     *     * removes html
     *     * truncates to 150-char limit
     *     * aggressively removes whitespaces
     *
     * I think all titles are subjected to this limit secretly
     *
     * @param string $title
     * @return string
     */
    public function produceTitle(string $title): string
    {
        /* Aggressively truncate because learnosity doesn't support multibyte characters properly (still)
         * This is how emojis destroyed imports in 2023
         *
         * First, try to safely truncate with mb_substr
         */
        $newTitle = trim(html_entity_decode(mb_substr(trim(strip_tags($title)), 0, self::LEARNOSITY_MAX_TITLE_LENGTH)));

        /* If it's -still- too long, use regular substr to snip off the last couple of chars
         * I know this means we might cut off a multibyte char that exists right at the end.
         * The alternative would be to arbitrarily make the maxlength 100 instead of 150,
         * but then all you need is 50 hiragana characters and you're back here again trying
         * to solve this issue
         */
        if (self::LEARNOSITY_MAX_TITLE_LENGTH < strlen($newTitle)) {
            $newTitle = substr($newTitle, 0, self::LEARNOSITY_MAX_TITLE_LENGTH);
        }

        return $newTitle;
    }


    /**
     * Set up a payload for a single activity
     *
     * @param array $legacyQuiz
     * @param array $tags
     * @return array
     */
    private function setupActivityNode(array $legacyQuiz, array $tags): array
    {
        $struct = [
            'data' => [
                'config' => [
                    'regions' => Helper::LEARNOSITY_CONFIG_PLAYER_REGIONS,
                    /* Time (duration)
                     */
                    'time' => [
                        /* Force submission when time expires
                         * TODO may be controversial but seems desired, since it minimises
                         *  the quantity of "unfinalised attempts"
                         */
                        'limit_type' => 'hard',
                        /* Show countdown timer
                         * TODO may be controversial (stressful)
                         */
                        'countdown_option' => true
                    ]
                ],
                /* Fill in after the fact
                 */
                'items' => [],
                'rendering_type' => 'assess'
            ],
            /* A unique uuid in learnosity land that we can arbitrarily assign here
             */
            'reference' => (string)Uuid::uuid4(),
            /* Always spawn in PUBLISHED state
             */
            'status' => Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED,
            /* Tags
             */
            'tags' => $tags,
            /* The name of the quiz
             */
            'title' => $this->produceTitle($legacyQuiz['title']),
        ];

        if ((int)$legacyQuiz['duration']) {
            /* If a duration was set, configure it similarly
             *
             * Legacy quiz used -1 to mean "No Duration" but in learnosity, this is achieved
             * by using zero. A bug in learnosity's api allows you to compel an activity
             * to have -1 as a duration which ended up equating to -1hr59min lol
             * https://help.learnosity.com/hc/en-us/requests/23267
             */
            $struct['data']['config']['time']['max_time'] = max((int)$legacyQuiz['duration'], 0);

            /* Warn users when 60 seconds remains
             */
            $struct['data']['config']['time']['warning_time'] = 60;
        }

        return $struct;
    }


    /**
     * Set up a payload for a single item
     * (this will be vaguely synonymous with our legacy Section idea)
     *
     * @param array $section
     * @param array $tags
     * @return array
     */
    private function setupItemNode(array $section, array $tags): array
    {
        return [
            /* This decides where a feature/question appears in a layout
             */
            'definition' => [
                'widgets' => []
            ],
            /* Features and Questions are indexed separately
             */
            'features' => [],
            'questions' => [],
            /* A unique uuid in learnosity land that we can arbitrarily assign here
             */
            'reference' => (string)Uuid::uuid4(),
            /* Always spawn in PUBLISHED state
             */
            'status' => Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED,
            /* Tags
             */
            'tags' => $tags,
            /* The name of the quiz
             */
            'title' => $this->produceTitle($section['title']),
        ];
    }


    /**
     * Optional section Description
     *
     * If it was more than an empty string, make a placeholder Passage thing
     * Otherwise skip it; there's nothing to say
     */
    private function setupSectionFeatureNode(array $section, array $tags): array
    {
        $description = trim((string)$section['description']);
        $title = trim((string)$section['title']);

        if ('' !== $description) {
            return [
                'data' => [
                    'content' => $description,
                    'heading' => $title,
                    'type' => 'sharedpassage',
                ],
                'reference' => (string)UUid::uuid4(),
                'type' => 'sharedpassage',
            ];
        }

        return [];
    }

    /**
     * Set up a payload for a Question that lives in an Item.
     * $question comes with $answers too, from our db
     *
     * @param array $question
     * @return array
     * @throws \DomainException
     */
    private function setupQuestionNode(array $question): array
    {
        $strategy = $this->strategyFactory->produce($question['type_id']);

        /* Unknown question type
         */
        if (null === $strategy) {
            throw new \DomainException(sprintf(
                'Unknown/unsupported question type %s in StrategyFactory when processing question %s in section_id %s',
                $question['type_id'],
                $question['question_id'],
                $question['section_id']
            ));
        }

        return [
            'data' => $strategy->convert($question),
            'type' => $strategy->getLearnosityEquivalent(),
            'reference' => (string)UUid::uuid4(),
        ];
    }
}
