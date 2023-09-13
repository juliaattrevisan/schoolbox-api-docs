<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\ActivityBuilder;
use Schoolbox\Learning\Learnosity\Importer\Strategy\MatchingPairs;
use Schoolbox\Learning\Learnosity\Importer\Strategy\Multi;
use Schoolbox\Learning\Learnosity\Importer\Strategy\Radio;
use Schoolbox\Learning\Learnosity\TagFactory;

class ImporterTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'learnosity_migration';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --assessment
     *
     * Import a particular quiz from the schoolbox.assessment table
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';


    /**
     * --blueprint
     *
     * Show the payload we intend to send to learnosity land
     */
    private const OPT_LONG_BLUEPRINT = 'blueprint';


    /**
     * --preserve-sections
     *
     * Decide whether to preserve sections or not.
     *
     * Preserving sections interprets legacy quiz Sections as a single
     * Learnosity Item, but is detrimental to question reuse and
     * horizontal marking.
     */
    private const OPT_LONG_PRESERVE_SECTIONS = 'preserve-sections';


    /**
     * Learnosity has some strict requirements about where api requests originate
     */
    private const DEFAULT_SERVER_NAME = 'vagrant.dev.schoolbox.cloud';


    /* For prepared statements
     */

    /**
     * Get all the schoolbox.assess rows that aren't part of Learnosity Land already
     * This is the default mechanism by which to mass-import everything
     */
    private const QUERY_LEGACY_QUIZ_IDS = "
        SELECT assess_id
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND learnosity_reference IS NULL
    ";


    /**
     * Get a particular the schoolbox.assess row
     *
     * Note that using this will arbitrarily spawn a new quiz each and every time and should
     * be used carefully; you will be creating orphaned data by repeatedly using this. This
     * tool will not archive something it is about to replace.
     */
    private const QUERY_LEGACY_QUIZ_ID = "
        SELECT assess_id
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND assessment_id = :assessmentId
    ";


    /**
     * Get a particular schoolbox.assess row
     */
    private const QUERY_QUIZ = "
        SELECT *
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND assess_id = :assessId
    ";


    /**
     * Get the sections in a particular schoolbox.assess
     */
    private const QUERY_SECTION = '
        SELECT *
        FROM assess_section
        WHERE deleted_at IS NULL
        AND assess_id = :assessId
        ORDER BY sequence ASC
    ';


    /**
     * Get the questions in a particular schoolbox.assess_section
     */
    private const QUERY_QUESTION = '
        SELECT *
        FROM assess_question
        WHERE deleted_at IS NULL
        AND section_id = :sectionId
        ORDER BY sequence ASC
    ';


    /**
     * Get the answers in a particular schoolbox.assess_question
     */
    private const QUERY_ANSWER = '
        SELECT *
        FROM assess_answer
        WHERE deleted_at IS NULL
        AND question_id = :questionId
        ORDER BY sequence ASC
    ';


    /**
     * Associate a newly-imported learnosity quiz with the legacy one
     */
    private const QUERY_ASSOCIATE_LEARNOSITY = '
        UPDATE assess
        SET learnosity_reference = :learnosityReference
        WHERE assess_id = :assessId
        LIMIT 1
    ';


    /**
     * A set of prepared statements we can recycle
     *
     * @var \PDOStatement
     */
    private \PDOStatement $legacyQuizIdsStatement;
    private \PDOStatement $legacyQuizIdStatement;
    private \PDOStatement $quizStatement;
    private \PDOStatement $sectionsStatement;
    private \PDOStatement $questionStatement;
    private \PDOStatement $answerStatement;
    private \PDOStatement $associateStatement;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_BLUEPRINT,
            self::OPT_LONG_PRESERVE_SECTIONS,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Learnosity Migrator

Replicates legacy quizzes from schoolbox.assess into its modern Learnosity incarnation.
Each question type and contents is mapped to their closest Learnosity synonyms.

You can specify a particular assessment to import; otherwise it imports everything
that is:
 * alive (ie. everything that is not soft-deleted)
 * not already in learnosity (ie. assess.learnosity_reference is null) 

Use --blueprint option in either mode to show the array struct we intended to send to Learnosity
dumped to the cli. This is useful for manual inspection before committing to a --perform  

Usage: import.php [--assessment <assessmentId>] [--perform]
Example: import.php --assessment 123456 --perform
Example: import.php --assessment 123456 --blueprint

HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        /* Don't have access to $_SERVER superglobal here
         * TODO Maybe make this into a cli arg if you use this outside of vagrant
         */
        $serverName = self::DEFAULT_SERVER_NAME;

        $schoolCode = $this->getConfig()->get('site_school_code');

        /* Initialise dependencies
         */
        $api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        /* Generates sets of tags for Activities' and Items' assignations
         */
        $tagFactory = new TagFactory($schoolCode);

        /* Generates payload "blueprints" so you can inspect them before committing to
         * sending them to Learnosity
         */
        $activityBuilder = new ActivityBuilder($schoolCode, $tagFactory, $this->getLogger());

        /* Capture basic metrics
         */
        $timing = [];

        /* Precompile queries
         */
        $this->legacyQuizIdsStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_QUIZ_IDS);
        $this->legacyQuizIdStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_QUIZ_ID);
        $this->quizStatement = $this->getDatabaseInternal()->prepare(self::QUERY_QUIZ);
        $this->sectionsStatement = $this->getDatabaseInternal()->prepare(self::QUERY_SECTION);
        $this->questionStatement = $this->getDatabaseInternal()->prepare(self::QUERY_QUESTION);
        $this->answerStatement = $this->getDatabaseInternal()->prepare(self::QUERY_ANSWER);
        $this->associateStatement = $this->getDatabaseInternal()->prepare(self::QUERY_ASSOCIATE_LEARNOSITY);

        /* Obtain data set.
         * If --assessment was specified, find its subobject and work with that.
         * Otherwise collect the whole set of non-deleted quizzes via a custom query.
         *
         * TODO Maybe expand this to include a set of assessmentIds.
         */
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT_ID));

        if ('' === $assessmentId) {
            /* Get everything
             */
            $legacyQuizIds = $this->getLegacyQuizIds();
        } else {
            /* Get $assessmentId
             */
            $legacyQuizIds = $this->getLegacyQuizIds((int)$assessmentId);
        }

        /* Commencing migration
         */
        $this->getLogger()->info('Learnosity Migration.');
        $this->getLogger()->info('');

        /*
            foreach $quiz in $quizzes
                $this->refreshLock();

                set up an Activity payload
                https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities

                foreach $section in $quiz
                    $this->refreshLock();

                    set up an Item payload
                    https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setItems

                    foreach $question in $section
                        $this->refreshLock();

                        set up a new Question payload
                        https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
                            - detect question type
                            - spawn importer accordingly
                            - importer->import()

                        you'll probably need its answers, too, from assess_answer
                        which you need to deserialize. It's not particularly obvious
                        what format this payload should be in, but try this:
                        https://reference.learnosity.com/questions-api/questiontypes

                    send the Item containing multiple Questions into Learnosity itself?
                    https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setItems

                send the whole Activity, its Items and its Questions into Learnosity
                https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities

                if successful, update the learnosity_reference so we know it ended up in learnosity
                otherwise something went wrong and this row is still a candidate for import
        */
        try {
            foreach ($legacyQuizIds as $legacyQuizId) {
                $this->refreshLock($lock);

                /* Get the legacy quiz's contents
                 */
                $legacyQuiz = $this->getQuiz($legacyQuizId);

                /* Announce what we're doing
                 */
                $this->getLogger()->info(sprintf(
                    'Processing "%s" (assess_id: %s)',
                    $legacyQuiz['title'],
                    $legacyQuiz['assess_id']
                ));

                /* If this doesn't have an assessment_id, idk what this is,
                 * because you can't get here anymore.
                 */
                if ('' === trim((string)$legacyQuiz['assessment_id'])) {
                    $this->getLogger()->warning(sprintf(
                        'Missing assessment_id; skipping'
                    ));

                    continue;
                }

                /* Make the overall Learnosity payloads which we then send later with
                 * a little postprocessing to put things into the exactly correct format.
                 * Learnosity is really particular about such things.
                 */
                try {
                    /* Choose which construction style you want
                     */
                    if ($this->getOpt(self::OPT_LONG_PRESERVE_SECTIONS)) {
                        $blueprint = $activityBuilder->buildClassically($legacyQuiz);
                    } else {
                        $blueprint = $activityBuilder->buildFlattened($legacyQuiz);
                    }
                } catch (\DomainException $e) {
                    $this->getLogger()->warning($e->getMessage());
                    $this->getLogger()->warning(sprintf(
                        'Skipping assess_id %s',
                        $legacyQuiz['assess_id']
                    ));

                    continue;
                }

                /* Optionally show array payloads
                 */
                if ($this->getOpt(self::OPT_LONG_BLUEPRINT)) {
                    print_r($blueprint);
                }

                /* Actually do it
                 */
                if ($this->perform()) {
                    $this->getLogger()->info('Importing into Learnosity. This might take a while.');

                    $start = microtime(true);

                    /* Iterate and bombard Learnosity with api requests pewpewpew
                     */
                    foreach ($blueprint['payloadSequence'] as $data) {
                        $this->getLogger()->debug(sprintf(
                            '%s: transmitting payload to learnosity...',
                            $data['endpoint']
                        ));

                        $api->dataSetRequest($data['endpoint'], $data['payload']);
                    }

                    /* Associate the learnosity reference back to our legacy quiz
                     */
                    $this->getLogger()->info(sprintf(
                        'Associating legacy quiz with learnosity_reference %s',
                        $blueprint['activityLearnosityReference']
                    ));

                    $this->associateStatement->execute([
                        'learnosityReference' => $blueprint['activityLearnosityReference'],
                        'assessId' => $legacyQuizId
                    ]);

                    $end = microtime(true);

                    /* Capture metrics
                     */
                    $timing[] = $end - $start;
                }

                $this->getLogger()->info(sprintf(
                    'Finished processing "%s" (assess_id: %s)',
                    $legacyQuiz['title'],
                    $legacyQuiz['assess_id']
                ));

                $this->getLogger()->info('');
            }
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

        /* Report some metrics
         */
        $numImported = \count($timing);

        if (!$numImported) {
            $this->getLogger()->info('No quizzes imported; none found. Exiting.');
            return;
        }

        $this->getLogger()->info(sprintf(
            'Number of quizzes imported: %s. Average time taken per quiz: approx. %s seconds',
            $numImported,
            round(array_sum($timing) / $numImported)
        ));

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Get a set of all schoolbox.assess primary identifiers because
     * we need to process each quiz individually:
     *     * and utilise the lock mechanism
     *     * and avoid loading EVERYTHING into memory
     *
     * TODO potentially replace this with a QueryIterator but for now this will do
     *
     * @param int|null $assessmentId
     * @return array
     * @throws \PDOException
     */
    private function getLegacyQuizIds(?int $assessmentId = null): array
    {
        if (null === $assessmentId) {
            $statement = $this->legacyQuizIdsStatement;
            $statement->execute();
        } else {
            $statement = $this->legacyQuizIdStatement;
            $statement->execute([
                'assessmentId' => $assessmentId
            ]);
        }

        $ids = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[$row['assess_id']] = $row['assess_id'];
        }

        return array_values($ids);
    }


    /**
     * Get a quiz that:
     *     * is not deleted
     *     * is not yet in learnosity
     *
     * @param int $assessId
     * @return array
     * @throws \JsonException
     * @throws \PDOException
     */
    private function getQuiz(int $assessId): array
    {
        $this->quizStatement->execute([
            'assessId' => $assessId
        ]);

        /* Final stack
         */
        $quizRow = $this->quizStatement->fetch(\PDO::FETCH_ASSOC);
        $quizRow['_sections'] = [];

        /* Get its sections
         */
        $this->sectionsStatement->execute([
            'assessId' => $quizRow['assess_id']
        ]);

        while ($sectionRow = $this->sectionsStatement->fetch(\PDO::FETCH_ASSOC)) {
            $sectionRow['_questions'] = [];

            /* Get each section's questions
             */
            $this->questionStatement->execute([
                'sectionId' => $sectionRow['section_id']
            ]);

            while ($questionRow = $this->questionStatement->fetch(\PDO::FETCH_ASSOC)) {
                $questionRow['_answers'] = [];

                /* Decompile config
                 */
                $questionRow['config'] = $this->decompileQuestionConfig($questionRow);

                /* Get each question's answers
                 */
                $this->answerStatement->execute([
                    'questionId' => $questionRow['question_id']
                ]);

                while ($answerRow = $this->answerStatement->fetch(\PDO::FETCH_ASSOC)) {
                    $answerRow['answer'] = $this->decompileAnswer($questionRow, $answerRow);

                    $questionRow['_answers'][] = $answerRow;
                }

                $sectionRow['_questions'][] = $questionRow;
            }

            $quizRow['_sections'][] = $sectionRow;
        }

        return $quizRow;
    }


    /**
     * Question Configurations hid some settings which we can use in Learnosity Land
     *
     * @param array $question
     * @return mixed
     * @throws \JsonException
     */
    private function decompileQuestionConfig(array $question): mixed
    {
        if ('' !== (string)$question['config']) {
            return json_decode($question['config'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $question['config'];
    }


    /**
     * Certain question types were supposed to have serialized answers
     * These were never really treated like a codec and the handlers
     * are intertwined with phplibs, which is annoying
     *
     * @param array $question
     * @param array $answer
     * @return mixed
     */
    private function decompileAnswer(array $question, array $answer): mixed
    {
        /* Quiz only uses types 2, 4 and 5
         * (6,8,9,10,17 are all Survey)
         */
        $serializedAnswers = [
            Multi::QUESTION_TYPE_ID,
            MatchingPairs::QUESTION_TYPE_ID,
            Radio::QUESTION_TYPE_ID,
        ];

        if (in_array($question['type_id'], $serializedAnswers, false)) {
            return unserialize($answer['answer'], [
                'allowed_classes' => true
            ]);
        }

        return $answer['answer'];
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
