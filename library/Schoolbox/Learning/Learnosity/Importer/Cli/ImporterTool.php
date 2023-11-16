<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Alaress\Lock\LockRefreshException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\BlueprintBuilder;
use Schoolbox\Learning\Learnosity\Importer\ContextLookup;
use Schoolbox\Learning\Learnosity\Importer\LegacyQuizBuilder;
use Schoolbox\Learning\Learnosity\Importer\PayloadFactory;
use Schoolbox\Learning\Learnosity\Importer\Strategy;
use Schoolbox\Learning\Learnosity\TagFactory;
use Symfony\Component\Lock\LockInterface;

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
     * --small
     *
     * Import multiple small quizzes that can fit into a single learnosity payload,
     * consolidating them, and minimising the number of payloads required to send
     * all our data up to learnosity (each api requests costs 2 seconds)
     */
    private const OPT_LONG_IMPORT_SMALL_QUIZZES = 'small';


    /**
     * --large
     *
     * Import large quizzes that exceed learnosity's maximum payload size
     */
    private const OPT_LONG_IMPORT_LARGE_QUIZZES = 'large';


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


    /* For prepared statements
     */

    /**
     * Maximum number of things per learnosity payload
     * (50 questions, 50 features, 50 items, 50 activities)
     */
    private const LEARNOSITY_MAX_PAYLOAD_SIZE = 15;


    /**
     * Quizzes that have less than LEARNOSITY_MAX_PAYLOAD_SIZE questions in them
     * Learnosity payloads can potentially fit multiple quizzes' data in them
     *
     * Get all the schoolbox.assess rows that aren't part of Learnosity Land already
     * This is the default mechanism by which to mass-import everything.
     *
     * The ORDER BY clause loads the most recent quizzes first, and the oldest quizzes last
     * so that schools can still keep going while this imports in the background.
     *
     * TODO Alternatively/additionally you can ignore quizzes that fulfil some set of
     *  conditions, minimising the size of the dataset.
     */
    private const QUERY_LEGACY_SMALL_QUIZ_IDS = "
        SELECT assess.assess_id
        FROM assess 
        INNER JOIN assess_section section ON assess.assess_id = section.assess_id
        INNER JOIN assess_question question ON section.section_id = question.section_id
        WHERE assess.deleted_at IS NULL
        AND assess.availability = 'assess'
        AND assess.learnosity_reference IS NULL
        AND assess.external_id IS NULL
        GROUP BY assess.assess_id
        HAVING COUNT(question.question_id) <= :payloadSize
        ORDER BY assess.created_at DESC
    ";


    /**
     * Quizzes that have at least LEARNOSITY_MAX_PAYLOAD_SIZE questions in them
     * They're too big to fit in one payload, so we have to spread them out
     *
     * Get all the schoolbox.assess rows that aren't part of Learnosity Land already
     * This is the default mechanism by which to mass-import everything.
     *
     * The ORDER BY clause loads the most recent quizzes first, and the oldest quizzes last
     * so that schools can still keep going while this imports in the background.
     *
     * TODO Alternatively/additionally you can ignore quizzes that fulfil some set of
     *  conditions, minimising the size of the dataset.
     */
    private const QUERY_LEGACY_LARGE_QUIZ_IDS = "
        SELECT assess.assess_id
        FROM assess 
        INNER JOIN assess_section section ON assess.assess_id = section.assess_id
        INNER JOIN assess_question question ON section.section_id = question.section_id
        WHERE assess.deleted_at IS NULL
        AND assess.availability = 'assess'
        AND assess.learnosity_reference IS NULL
        AND assess.external_id IS NULL
        GROUP BY assess.assess_id
        HAVING COUNT(question.question_id) > :payloadSize
        ORDER BY assess.created_at DESC
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
        AND external_id IS NULL
        AND availability = 'assess'
        AND assessment_id = :assessmentId
    ";


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
    private \PDOStatement $associateStatement;


    /**
     * A list of endpoints (both ours and Learnosity's) for metrics
     */
    private const ENDPOINT_INDEX = [
        BlueprintBuilder::ENDPOINT_FEATURES,
        BlueprintBuilder::ENDPOINT_QUESTIONS,
        BlueprintBuilder::ENDPOINT_ITEMS,
        BlueprintBuilder::ENDPOINT_ACTIVITIES,
        BlueprintBuilder::ENDPOINT_ASSOCIATIONS
    ];


    /**
     * Some meaningful stats to show at the end of the import
     *
     * @var array
     */
    private array $timing = [];
    private array $metrics = [];
    private int $numBatches = 0;
    private int $numQuizzesImported = 0;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_BLUEPRINT,
            self::OPT_LONG_IMPORT_SMALL_QUIZZES,
            self::OPT_LONG_IMPORT_LARGE_QUIZZES,
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

As usual, verbose mode tells you all the debugging-level info about what the algorithm does.

Usage: import.php --small|large [--assessment <assessmentId>] [--perform] [-vvv]

Example: import.php --small --assessment=123456 --perform
Example: import.php --small --assessment=123456 --blueprint -vvv

Example: import.php --large --assessment=9876 --perform
Example: import.php --large --assessment=9876 --blueprint -vvv

HELP;
            return;
        }

        /* Check mandatory args first
         */
        if (!$this->getOpt(self::OPT_LONG_IMPORT_SMALL_QUIZZES) && !$this->getOpt(self::OPT_LONG_IMPORT_LARGE_QUIZZES)) {
            $this->getLogger()->info(sprintf(
                'Please select either --%s or --%s quiz sizes to import.',
                self::OPT_LONG_IMPORT_SMALL_QUIZZES,
                self::OPT_LONG_IMPORT_LARGE_QUIZZES
            ));

            exit;
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

        /* Used to segregate each school's data; used in tags and author identifiers
         */
        $schoolCode = $this->getConfig()->get('site_school_code');

        /* Initialise dependencies
         * Note: we don't have access to $_SERVER superglobal here
         */
        $server = (new \Schoolbox\Url\Factory($this->getConfig()))->buildFullyQualified();
        $serverName = $server->getHost();
        $domain = (string)$server;

        $api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        /* Used to manufacture an array containing all the legacy quiz's contents
         */
        $legacyQuizBuilder = new LegacyQuizBuilder($this->getDatabaseInternal());

        /* Generates sets of tags for Activities' and Items' assignations
         */
        $tagFactory = new TagFactory($schoolCode);

        /* A context lookup builder thing for conveniently deriving tags
        */
        $contextLookup = new ContextLookup($this->getDatabaseInternal(), $tagFactory, $this->getLogger());

        /* Generates payload array structs
         */
        $payloadFactory = new PayloadFactory(new Strategy\Factory($domain));

        /* Generates payload "blueprints" so you can inspect them before committing to
         * sending them to Learnosity
         */
        $blueprintBuilder = new BlueprintBuilder($schoolCode, $payloadFactory, $tagFactory, $contextLookup, $this->getLogger());

        /* Capture basic metrics
         */
        $this->metrics = array_fill_keys(self::ENDPOINT_INDEX, ['count' => 0, 'duration' => 0]);

        /* Precompile queries
         *
         * The legacy quiz ids are obtained based on which mode you're in,
         * whether you're importing small quizzes or large quizzes.
         * (Different selection crtieria apply due to payload size limitation)
         */
        if ($this->getOpt(self::OPT_LONG_IMPORT_SMALL_QUIZZES)) {
            $this->legacyQuizIdsStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_SMALL_QUIZ_IDS);
        } elseif ($this->getOpt(self::OPT_LONG_IMPORT_LARGE_QUIZZES)) {
            $this->legacyQuizIdsStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_LARGE_QUIZ_IDS);
        }

        $this->legacyQuizIdStatement = $this->getDatabaseInternal()->prepare(self::QUERY_LEGACY_QUIZ_ID);
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

        $overallStart = microtime(true);

        if (\count($legacyQuizIds)) {
            if ($this->getOpt(self::OPT_LONG_IMPORT_SMALL_QUIZZES)) {
                $this->importSmall(
                    $lock,
                    $legacyQuizBuilder,
                    $blueprintBuilder,
                    $api,
                    $legacyQuizIds
                );
            } elseif ($this->getOpt(self::OPT_LONG_IMPORT_LARGE_QUIZZES)) {
                $this->importLarge(
                    $lock,
                    $legacyQuizBuilder,
                    $blueprintBuilder,
                    $api,
                    $legacyQuizIds
                );
            }
        }

        /* Capture overall time spent importing
         */
        $overallEnd = microtime(true);
        $totalDuration = $overallEnd - $overallStart;

        /* Report some metrics
         */
        if (!$this->numQuizzesImported) {
            $this->getLogger()->info('No quizzes imported; none found. Exiting.');
            return;
        }

        /* Endpoint usage
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Migration Summary');
        $this->getLogger()->info('');

        if ($this->perform()) {
            /* Total time spent here
             */
            $this->getLogger()->info(sprintf(
                '    Total time spent importing: approx. %s seconds',
                round($totalDuration)
            ));

            /* Timing stats only available in perform mode
             */
            $this->getLogger()->info(sprintf(
                '    Average time taken per quiz: approx. %s seconds',
                round(array_sum($this->timing) / $this->numQuizzesImported)
            ));

            $this->getLogger()->info('');
        }

        /* Number of quizzes imported
         */
        $this->getLogger()->info(sprintf(
            '    Number of quizzes imported: %s',
            $this->numQuizzesImported
        ));

        /* Number of batches used
         */
        $this->getLogger()->info(sprintf(
            '    Number of batches used: %s',
            $this->numBatches
        ));

        $this->getLogger()->info(sprintf(
            '    Average quizzes per batch: %s',
            number_format($this->numQuizzesImported / $this->numBatches, 2)
        ));

        $this->getLogger()->info('');

        foreach ($this->metrics as $endpoint => $data) {
            $this->getLogger()->info(sprintf(
                '    %s: %s objects sent (approx. %s seconds total)',
                $endpoint,
                $data['count'],
                number_format($data['duration'], 3)
            ));
        }

        $this->getLogger()->info('');

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


    /* Algorithmic approaches:
     * Consolidate, for packing many small quizzes into as few payloads as possible
     * Distribute, for unpacking large quizzes over multiple payloads
     */

    /**
     *
     * foreach $quiz in $quizzes
     *      $this->refreshLock();
     *
     *      set up an Activity payload
     *      https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities
     *
     *      foreach $section in $quiz
     *          $this->refreshLock();
     *
     *          set up an Item payload
     *          https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setItems
     *
     *          foreach $question in $section
     *              $this->refreshLock();
     *
     *              set up a new Question payload
     *              https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
     *              - detect question type
     *              - spawn importer accordingly
     *              - importer->import()
     *
     *              you'll probably need its answers, too, from assess_answer
     *              which you need to deserialize. It's not particularly obvious
     *              what format this payload should be in, but try this:
     *              https://reference.learnosity.com/questions-api/questiontypes
     *
     *              send the Item containing multiple Questions into Learnosity itself?
     *              https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setItems
     *
     *              send the whole Activity, its Items and its Questions into Learnosity
     *              https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities
     *
     *              if successful, update the learnosity_reference so we know it ended up in learnosity
     *              otherwise something went wrong and this row is still a candidate for import
     *
     * @param LockInterface $lock
     * @param LegacyQuizBuilder $legacyQuizBuilder
     * @param BlueprintBuilder $blueprintBuilder
     * @param Api $api
     * @param array $legacyQuizIds
     * @return void
     */
    private function importSmall(
        LockInterface $lock,
        LegacyQuizBuilder $legacyQuizBuilder,
        BlueprintBuilder $blueprintBuilder,
        Api $api,
        array $legacyQuizIds
    ): void {
        try {
            /* $legacyQuizIds is a bunch of identifiers
             *
             * We can perhaps minimise more Learnosity requests by batching $blueprints
             * which typically represent a single quiz. There's a limit on the number of
             * quizzes, but there's also a limit on number of features/questions/items
             * which isn't particularly obvious
             */
            $consolidatedBlueprint = null;

            foreach ($legacyQuizIds as $legacyQuizId) {
                /* Do all the stuff it used to do to produce a $blueprint
                 */
                $this->refreshLock($lock);

                /* Get the legacy quiz's contents
                 */
                $legacyQuiz = $legacyQuizBuilder->build($legacyQuizId);

                /* Announce what we're doing
                 */
                $this->getLogger()->info(sprintf(
                    'Processing "%s" (assessment_id: %s, assess_id: %s)...',
                    $legacyQuiz['title'],
                    $legacyQuiz['assessment_id'],
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
                        $blueprint = $blueprintBuilder->buildPreserveSections($legacyQuiz);
                    } else {
                        $blueprint = $blueprintBuilder->buildFlattenSections($legacyQuiz);
                    }
                } catch (\DomainException $e) {
                    $this->getLogger()->warning($e->getMessage());
                    $this->getLogger()->warning(sprintf(
                        'Skipping assess_id %s',
                        $legacyQuiz['assess_id']
                    ));

                    continue;
                }

                /* Consolidate 1 quiz to start with
                 *
                 * Stack them up by their various strata -
                 * all the features get combined, all the questions get combined,
                 * all the items get combined and all the activities get combined.
                 */
                if (null === $consolidatedBlueprint) {
                    $consolidatedBlueprint = $blueprintBuilder->consolidateNew($blueprint);
                    $this->numQuizzesImported++;

                    $this->getLogger()->debug('Consolidating initial quiz and continuing onwards');

                    continue;
                }

                /* Fetch the next quiz and perform a similar inspection
                 * If adding it allows all payloads to be under their limit,
                 * keep repeating this process until $nextQuiz causes one
                 * of the payloads to exceed the limit
                 */
                $canConsolidate = $blueprintBuilder->canConsolidate($consolidatedBlueprint, $blueprint);

                if ($canConsolidate) {
                    $consolidatedBlueprint = $blueprintBuilder->consolidate($consolidatedBlueprint, $blueprint);
                    $this->numQuizzesImported++;

                    $this->getLogger()->debug('Consolidating quiz and continuing onwards');

                    continue;
                }

                $this->getLogger()->info('Consolidation full; this quiz goes into next batch.');

                /* We've got a full $consolidatedBlueprints; we can't add another
                 * Start doing some real work with Learnosity
                 */
                $this->getLogger()->debug('Transmitting existing consolidation and setting this quiz for next consolidation');

                /* Send it
                 */
                $this->transmitConsolidated($api, $consolidatedBlueprint);

                /* Make the next $consolidatedBlueprint start with $blueprint
                 * Rinse and repeat until all is done
                 */
                $consolidatedBlueprint = $blueprintBuilder->consolidateNew($blueprint);

                /* Log counts as we progress
                 */
                $this->numQuizzesImported++;
            }

            /* Remainder data
             * Transmit the last consolidated payload
             */
            $this->getLogger()->debug('Transmitting final consolidated remainder');

            /* Send it
             */
            $this->transmitConsolidated($api, $consolidatedBlueprint);
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            exit;
        } catch (\Exception $e) {
            /* Some other random death
             */
            $this->getLogger()->warning($e->getMessage());
            exit;
        }
    }


    /**
     * Import large quizzes by spreading their guts out over multiple payloads
     *
     * @param LockInterface $lock
     * @param LegacyQuizBuilder $legacyQuizBuilder
     * @param BlueprintBuilder $blueprintBuilder
     * @param Api $api
     * @param array $legacyQuizIds
     * @return void
     * @throws LockRefreshException
     */
    private function importLarge(
        LockInterface $lock,
        LegacyQuizBuilder $legacyQuizBuilder,
        BlueprintBuilder $blueprintBuilder,
        Api $api,
        array $legacyQuizIds
    ): void
    {
        /* $legacyQuizIds is a bunch of identifiers
         *
         * We can perhaps minimise more Learnosity requests by batching $blueprints
         * which typically represent a single quiz. There's a limit on the number of
         * quizzes, but there's also a limit on number of features/questions/items
         * which isn't particularly obvious
         */
        foreach ($legacyQuizIds as $legacyQuizId) {
            /* Do all the stuff it used to do to produce a $blueprint
             */
            $this->refreshLock($lock);

            /* Get the legacy quiz's contents
             */
            $legacyQuiz = $legacyQuizBuilder->build($legacyQuizId);

            /* Announce what we're doing
             */
            $this->getLogger()->info(sprintf(
                'Processing "%s" (assessment_id: %s, assess_id: %s)...',
                $legacyQuiz['title'],
                $legacyQuiz['assessment_id'],
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
                    $blueprint = $blueprintBuilder->buildPreserveSections($legacyQuiz);
                } else {
                    $blueprint = $blueprintBuilder->buildFlattenSections($legacyQuiz);
                }
            } catch (\DomainException $e) {
                $this->getLogger()->warning($e->getMessage());
                $this->getLogger()->warning(sprintf(
                    'Skipping assess_id %s',
                    $legacyQuiz['assess_id']
                ));

                continue;
            }

            /* Distribute each blueprint payload into multiple learnosity payloads
             * but we don't know which payload is too big. This is the inverse of
             * consolidate()
             */
            foreach ($blueprint['payloadSequence'] as $payloadSequence) {
                $endpoint = $payloadSequence['endpoint'];

                if (BlueprintBuilder::ENDPOINT_FEATURES === $endpoint) {
                    if (\count($payloadSequence['payload']['features']) > self::LEARNOSITY_MAX_PAYLOAD_SIZE) {
                        $payload = [];
                    } else {
                        $payload = [];
                    }
                }
            }



            print_r($blueprint);die();
        }

        return;
    }


    /* Misc helpful stuff
     */

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
            $statement->execute([
                'payloadSize' => self::LEARNOSITY_MAX_PAYLOAD_SIZE
            ]);
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
     * Get some quantities of each endpoint we used
     *
     * @param array $consolidatedBlueprint
     */
    private function accumulateMetrics(array $consolidatedBlueprint): void
    {
        foreach (self::ENDPOINT_INDEX as $type) {
            $this->metrics[$type]['count'] += $consolidatedBlueprint[$type]['count'];
        }
    }


    /**
     * Sends data to Learnosity and returns updated metrics
     *
     * @param Api $api
     * @param array $consolidatedBlueprint
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    private function transmitConsolidated(Api $api, array $consolidatedBlueprint): void
    {
        /* Accumulate some basic metrics
         */
        $this->accumulateMetrics($consolidatedBlueprint);

        /* Optionally show array payloads
         */
        if ($this->getOpt(self::OPT_LONG_BLUEPRINT)) {
            print_r($consolidatedBlueprint);
        }

        /* When perform mode is on, actually send it as batches of features, questions,
         * items, activities and just...hope/presumed that everything worked out
         */
        if ($this->perform()) {
            $this->getLogger()->info(sprintf(
                'Importing %s consolidated quizzes into Learnosity. This might take a while.',
                $consolidatedBlueprint[BlueprintBuilder::ENDPOINT_ASSOCIATIONS]['count']
            ));

            /* Iterate and bombard Learnosity with api requests pewpewpew
             */
            $batchStart = microtime(true);

            foreach ($consolidatedBlueprint as $endpoint => $data) {
                $endpointStart = microtime(true);

                /* Associate the learnosity reference back to our legacy quiz
                 */
                if (BlueprintBuilder::ENDPOINT_ASSOCIATIONS === $endpoint) {
                    foreach ($data['payload']['associations'] as $association) {
                        $this->getLogger()->info(sprintf(
                            'Associating quiz (assessment_id: %s, assess_id: %s) with %s...',
                            $association['assessmentId'],
                            $association['quizId'],
                            substr($association['activityLearnosityReference'], 0, 13)
                        ));

                        $this->associateStatement->execute([
                            'learnosityReference' => $association['activityLearnosityReference'],
                            'assessId' => $association['quizId']
                        ]);
                    }
                } else {
                    $this->getLogger()->debug(sprintf(
                        '%s: transmitting payload (size: %s) to Learnosity...',
                        $data['endpoint'],
                        $data['count']
                    ));

                    /* Skip zero-sized payloads; Learnosity hates that
                     *
                     * (alternatively capture exception but probably not helpful most of the time)
                     *
                     * The main way this has happened to me is when you create a quiz
                     * using Learnosity, then artificially reset its learnosity_reference
                     * to null, then try to reimport that back into Learnosity - this is
                     * an exceedingly particular scenario that shouldn't happen organically.
                     *
                     * Or, there's just an empty section somehow.
                     */
                    if ($data['count'] > 0) {
                        $api->dataSetRequest($data['endpoint'], $data['payload']);
                    }
                }

                /* Capture endpoint-specific durations
                 */
                $endpointEnd = microtime(true);

                $this->metrics[$endpoint]['duration'] += $endpointEnd - $endpointStart;
            }

            $batchEnd = microtime(true);

            /* Capture overall metrics
             */
            $this->timing[] = $batchEnd - $batchStart;
        }

        /* Log counts as we progress
         */
        $this->numBatches++;

        $this->getLogger()->info(sprintf(
            'Batch %s done.',
            $this->numBatches
        ));

        $this->getLogger()->info('');
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
