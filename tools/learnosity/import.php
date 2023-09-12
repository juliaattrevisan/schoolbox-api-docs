<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\ActivityBuilder;
use Schoolbox\Learning\Learnosity\TagFactory;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
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
     * Learnosity has some strict requirements about where api requests originate
     */
    private const DEFAULT_SERVER_NAME = 'vagrant.dev.schoolbox.cloud';


    /* For prepared statements
     */

    /**
     * Get all the schoolbox.assess rows that aren't part of Learnosity Land already
     */
    private const QUERY_LEGACY_QUIZ_IDS = "
        SELECT assess_id
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND learnosity_reference IS NULL
    ";


    /**
     * Get a particular the schoolbox.assess row that isn't part of Learnosity Land already
     */
    private const QUERY_LEGACY_QUIZ_ID = "
        SELECT assess_id
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND learnosity_reference IS NULL
        AND assessment_id = :assessmentId
    ";


    /**
     * Get a particular schoolbox.assess row that not part of Learnosity already
     */
    private const QUERY_QUIZ = "
        SELECT *
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND learnosity_reference IS NULL
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
     * A set of prepared statements we can recylce
     *
     * @var PDOStatement
     */
    private \PDOStatement $legacyQuizIdsStatement;
    private \PDOStatement $legacyQuizIdStatement;
    private \PDOStatement $quizStatement;
    private \PDOStatement $sectionsStatement;
    private \PDOStatement $questionStatement;
    private \PDOStatement $answerStatement;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_BLUEPRINT,
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
that is alive (ie. everything that is not soft-deleted).

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
        $tagFactory = new TagFactory($this->getConfig()->get('site_school_code'));

        /* Generates payload "blueprints" so you can inspect them before committing to
         * sending them to Learnosity
         */
        $activityBuilder = new ActivityBuilder($tagFactory, $this->getLogger());

        /* Precompile queries
         */
        $this->legacyQuizIdsStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_QUIZ_IDS);
        $this->legacyQuizIdStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_QUIZ_ID);
        $this->quizStatement = $this->getDatabaseInternal()->prepare(self::QUERY_QUIZ);
        $this->sectionsStatement = $this->getDatabaseInternal()->prepare(self::QUERY_SECTION);
        $this->questionStatement = $this->getDatabaseInternal()->prepare(self::QUERY_QUESTION);
        $this->answerStatement = $this->getDatabaseInternal()->prepare(self::QUERY_ANSWER);

        /* Obtain data set.
         * If --assessment was specified, find its subobject and work with that.
         * Otherwise collect the whole set of non-deleted quizzes via a custom query.
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

                /* Make the overall Learnosity payloads which we then send later with
                 * a little postprocessing to put things into the exactly correct format.
                 * Learnosity is really particular about such things.
                 */
                try {
                    $blueprint = $activityBuilder->build($legacyQuiz);
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
                    $this->getLogger()->info(sprintf(
                        'Importing legacy quiz "%s" (assess_id: %s)',
                        $legacyQuiz['title'],
                        $legacyQuizId,
                    ));

                    /* Iterate and bombard Learnosity with api requests pewpewpew
                     */
                    foreach ($blueprint as $data) {
                        $api->dataSetRequest($data['endpoint'], $data['payload']);
                    }
                }
            }
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

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


//
//            /* Send it
//             */
//            $this->getLogger()->info(sprintf(
//                'Importing quiz "%s" (assessmentId: %s, assess_id: %s) with assess.learnosity_reference %s',
//                $legacyQuiz['title'],
//                $legacyQuiz['assessment_id'],
//                $legacyQuiz['assess_id'],
//                $activity['reference']
//            ));
//            /* Send it
//             */
//            $this->getLogger()->debug(sprintf(
//                'Importing item "%s" (section_id: %s) with learnosity_reference %s',
//                $section['title'],
//                $section['section_id'],
//                $item['reference']
//            ));


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
            2, // Multi
            4, // Match
            5, // Radio
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
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
