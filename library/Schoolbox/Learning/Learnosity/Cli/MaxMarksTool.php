<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use JsonException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api as LearnosityApi;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Url\Factory;

/**
 * Learnosity MaxMark Copier
 *
 * Copies the value of validation.valid_response.score into validation.max_score
 * because learnosity has two different contexts of automark: true and false,
 * each setting uses a different field to assign the maximum marks available.
 */
class MaxMarksTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * --assessment
     *
     * Alter a particular quiz from the schoolbox.assessment table
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';

    /**
     * --blueprint
     *
     * Show the payload we intend to send to learnosity land
     */
    private const OPT_LONG_BLUEPRINT = 'blueprint';

    /**
     * This is listed in a class const, but that class is being altered in another pull request atm
     */
    private const LEARNOSITY_SHORT_TEXT = 'shorttext';

    /**
     * Maximum number of items per payload.  This seems to be the same for all Learnosity data-api endpoints.
     * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
     */
    private const BATCH_SIZE_N_ITEMS = 50;

    /**
     * @var QuizMapper
     */
    private QuizMapper $quizMapper;

    /**
     * Used for performance metrics.
     */
    private ?float $apiRequestTotalWaitTimeSeconds = null;
    private ?float $apiUpdatesTotalWaitTimeSeconds = null;

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_BLUEPRINT,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Short Answer questions have an undocumented field: validation.max_score which can be used
to store the maximum marks available when automark is false. When automark is true, a different
field is used: validation.valid_response.score

This tool copies the one from validation.valid_response.score into validation.max_score so that
its value is everywhere and should support both cases of automark.

This tool can do this for all quiz questions in the database (this is the default behaviour),
or for a single question only (by supplying the --assessment=[assessmentId] option).

You can use the --blueprint arg to also show the payload we intend to send to learnosity.

Usage: maxmarks.php [--assessment=...] [-vvv] [--blueprint] [--perform]
Ex.    maxmarks.php --assessment=656 -vvv --perform

HELP;
            exit;
        }

        /* Log the title first lol
         */
        $logger = $this->getLogger();
        $logger->info('');
        $logger->info('Learnosity Attempt MaxMarksBulk copier');
        $logger->info('');

        /* Check mandatory args first
         */
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT_ID));

        if ('' === $assessmentId) {
            $assessmentIds = $this->getDatabaseInternal()->executeQuery(
                'SELECT id
                FROM assessment assm
                JOIN assess ass ON ass.assessment_id = assm.id
                WHERE assm.deleted_at IS NULL'
            )->fetchColumnArray('id');
        } else {
            $assessmentIds = [$assessmentId];
        }

        $scriptStartTime = microtime(true);
        $this->apiRequestTotalWaitTimeSeconds = 0;
        $this->apiUpdatesTotalWaitTimeSeconds = 0;

        $nAssessmentIds = count($assessmentIds);
        $logger->info("Found $nAssessmentIds assessmentIds.");
        $logger->info(''); // Blank line to make log readable.

        /* Build dependencies
         */
        $api = new LearnosityApi(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $logger,
        );

        /* Used to segregate each school's data; used in tags and author identifiers
         */
        $schoolCode = $this->getConfig()->get('site_school_code');

        $payload = [
            'meta' => [
                'user' => [
                    /* Just assume a system user
                     *  This is only ever used in learnosity land in some secret place
                     */
                    'id' => sprintf('%s_SA-17681', $schoolCode)
                ]
            ],
            'questions' => []
        ];

        /* We need these mappers
         */
        $this->quizMapper = QuizMapper::getInstance();

        $questions = $this->getQuestionsInfoFromLearnosity($api, $assessmentIds);

        $logger->info(''); // Blank line to make log readable.
        $logger->info('Updating questions:');

        $nQuestionsSkipped = 0;
        foreach ($questions as $question) {
            /* If the max_score value was already set, don't change anything;
             * the user might have done something here.
             */
            if (isset($question['data']['validation']['max_score'])) {
                $logger->debug(" * Question {$question['reference']} - Max score already set - skipping.");
                ++$nQuestionsSkipped;
                continue;
            }

            /* Assign max_score to be the same as validation.valid_response.score
             * ...because learnosity reasons
             */
            $updatedQuestion = $question;

            /* Short Text doesn't have a max_score field officially, so let's find out
             * if that's just a mistake, or if it really doesn't.
             */
            $updatedQuestion['data']['validation']['max_score'] = (
                $updatedQuestion['data']['validation']['valid_response']['score']
            );

            $logger->debug(
                sprintf(
                    ' * Question %s - Copying validation.valid_response.score %s to validation.max_score.',
                    $question['reference'],
                    $updatedQuestion['data']['validation']['valid_response']['score']
                )
            );

            $payload['questions'][] = $updatedQuestion;

            if (count($payload['questions']) === self::BATCH_SIZE_N_ITEMS) {
                // Maximum number of questions for API call reached.
                if (!$this->sendRequestToLearnosityIfPerforming($api, $payload)) {
                    $logger->info('Request to Learnosity failed, so stopping.');
                    die;
                }
                $payload['questions'] = []; // Clear questions array, so we can continue using $payload for future requests.
            }
        }

        if ($nQuestionsSkipped > 0) {
            $logger->info(" * Skipped $nQuestionsSkipped questions because max score already set.");
        }

        // If there are any updated questions that have not been sent to Learnosity, send them.
        if (count($payload['questions']) > 0) {
            if (!$this->sendRequestToLearnosityIfPerforming($api, $payload)) {
                $logger->info('Request to Learnosity failed, so stopping.');
            }
        }

        $scriptFinishTime = microtime(true);

        $logger->info('');
        $logger->info('Processed all ' . count($questions) . ' questions in ' . round($scriptFinishTime - $scriptStartTime, 3) . ' seconds.');
        $logger->info(round($this->apiRequestTotalWaitTimeSeconds, 3) . ' seconds was spent in information gathering API requests.');
        $logger->info(round($this->apiUpdatesTotalWaitTimeSeconds, 3) . ' seconds was spent in question updating API requests.');
    }

    /**
     * @param LearnosityApi $api
     * @param array         $assessmentIds
     * @return array
     */
    private function getQuestionsInfoFromLearnosity(LearnosityApi $api, array $assessmentIds): array
    {
        $logger = $this->getLogger();
        $logger->info('Gathering information from Learnosity:');

        $activityReferences = $this->getDatabaseInternal()->executeQuery(
            'SELECT assess.learnosity_reference
            FROM mv_assessment_relation
            JOIN assessment          ON mv_assessment_relation.assessment_id = assessment.id
            LEFT JOIN project        ON mv_assessment_relation.project_assessment_id = project.id
            LEFT JOIN lti_assessment ON assessment.id = lti_assessment.assessment_id
            LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
            LEFT JOIN task           ON task.assessment_id = assessment.id
            LEFT JOIN assess         ON assess.assessment_id = assessment.id
            LEFT JOIN lesson_plan    ON lesson_plan.assessment_id = assessment.id
            WHERE assessment.id IN (' . implode(',', array_map('intval', $assessmentIds)) . ')
            AND CONCAT(
               "assessment.",
               COALESCE(
                  IF(task.id, "task", NULL),
                  IF(assess.assessment_id, "quiz", NULL),
                  IF(lti_assessment.assessment_id, "lti", NULL),
                  IF(submission_box.id, "dueWork", NULL),
                  IF(project.id, "project", NULL),
                  IF(lesson_plan.id, "lessonPlan", NULL)
               )
            ) = "assessment.quiz"
            AND assessment.deleted_at IS NULL
            ORDER BY assessment.id ASC'
        )->fetchColumnArray('learnosity_reference');
        $logger->info(' * Found ' . count($activityReferences) . ' activity references.');

        $itemReferences = $this->getItemReferencesFromActivityReferences($api, $activityReferences);
        $logger->info(' * Found ' . count($itemReferences) . ' item references.');

        $questionReferences = $this->getShortTextQuestionReferencesFromItemReferences($api, $itemReferences);
        $logger->info(' * Found ' . count($questionReferences) . ' short text question references.');

        $questions = $this->getQuestionsFromQuestionReferences($api, $questionReferences);
        $logger->info(' * Found ' . count($questions) . ' short text questions.');

        return $questions;
    }

    /**
     * @param LearnosityApi $api
     * @param array         $activityReferences
     * @return array
     */
    private function getItemReferencesFromActivityReferences(LearnosityApi $api, array $activityReferences): array
    {
        $logger = $this->getLogger();
        $activityReferencesChunked = array_chunk($activityReferences, self::BATCH_SIZE_N_ITEMS);
        $itemReferences = [];

        foreach ($activityReferencesChunked as $activityReferences) {
            $logger->info(' * Requesting chunk of ' . count($activityReferences) . ' activities from /itembank/activities.');
            $t0 = microtime(true);
            try {
                // Docs: https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
                $response = $api->dataGetRequest('/itembank/activities', ['references' => $activityReferences]);
            } catch (JsonException | ValidationException $e) {
                $logger->error('Request to /itembank/activities failed.  ' . $e->getMessage());
                $logger->info('Continuing to next activity reference.');
                continue;
            }
            $this->apiRequestTotalWaitTimeSeconds += microtime(true) - $t0;

            $activities = $response['data'];

            foreach ($activities as $activity) {
                $activityReference = $activity['reference'] ?? null;

                if ($activityReference === null) {
                    $logger->error('Activity returned from Learnosity has no reference.');
                    $logger->info('Continuing to next activity references chunk.');
                    continue;
                }

                $activityData = $activity['data'] ?? [];
                $itemReferencesBatch = $activityData['items'] ?? [];

                if (null !== $itemReferencesBatch) {
                    /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
                     * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
                     */
                    if (is_array($itemReferencesBatch[0])) {
                        $itemReferencesBatch = array_column($itemReferencesBatch, 'id');
                    }
                }

                $itemReferences = array_merge($itemReferences, $itemReferencesBatch);
            }
        }

        return $itemReferences;
    }

    /**
     * @param LearnosityApi $api
     * @param array         $itemReferences
     * @return array
     */
    private function getShortTextQuestionReferencesFromItemReferences(LearnosityApi $api, array $itemReferences): array
    {
        $logger = $this->getLogger();
        $itemReferencesChunked = array_chunk($itemReferences, self::BATCH_SIZE_N_ITEMS);
        $shortTextQuestionReferences = [];

        foreach ($itemReferencesChunked as $itemReferences) {
            $logger->info(' * Requesting chunk of ' . count($itemReferences) . ' items from /itembank/items.');
            $t0 = microtime(true);
            try {
                // Docs: https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
                $response = $api->dataGetRequest('/itembank/items', ['references' => $itemReferences]);
            } catch (JsonException | ValidationException $e) {
                $logger->error('Request to /itembank/items failed.  ' . $e->getMessage());
                $logger->info('Continuing to next item references chunk.');
                continue;
            }
            $this->apiRequestTotalWaitTimeSeconds += microtime(true) - $t0;

            $items = $response['data'];

            foreach ($items as $item) {
                $itemReference = $item['reference'] ?? null;

                if ($itemReference === null) {
                    $logger->error('Item returned from Learnosity has no reference.');
                    $logger->info('Continuing to next item.');
                    continue;
                }

                $questionStubs = $item['questions'] ?? [];

                foreach ($questionStubs as $questionStub) {
                    if (self::LEARNOSITY_SHORT_TEXT === $questionStub['type']) {
                        $shortTextQuestionReferences[] = $questionStub['reference'];
                    }
                }
            }
        }

        return $shortTextQuestionReferences;
    }

    /**
     * @param LearnosityApi $api
     * @param array         $questionReferences
     * @return array
     */
    private function getQuestionsFromQuestionReferences(LearnosityApi $api, array $questionReferences): array
    {
        $logger = $this->getLogger();
        $questionReferencesChunked = array_chunk($questionReferences, self::BATCH_SIZE_N_ITEMS);
        $questions = [];

        foreach ($questionReferencesChunked as $questionReferences) {
            $logger->info(' * Requesting chunk of ' . count($questionReferences) . ' questions from /itembank/questions.');
            $t0 = microtime(true);
            try {
                // Docs: https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
                $response = $api->dataGetRequest('/itembank/questions', ['references' => $questionReferences]);
            } catch (JsonException | ValidationException $e) {
                $logger->error('   Request to /itembank/questions failed.  ' . $e->getMessage());
                $logger->debug('   Continuing to next question references chunk.');
                continue;
            }
            $this->apiRequestTotalWaitTimeSeconds += microtime(true) - $t0;

            $questionsBatch = $response['data'];
            $questions = array_merge($questions, $questionsBatch);
        }

        return $questions;
    }

    /**
     * If operating in 'perform' mode, send the given payload to Learnosity, and log actions.
     * If not operating in 'perform' mode, log the actions that would have been performed if operating in 'perform' mode.
     *
     * @param LearnosityApi $api
     * @param array $payload
     * @return bool Whether the request to Learnosity succeeded.  If not in 'perform' mode, this will always be true.
     */
    private function sendRequestToLearnosityIfPerforming(LearnosityApi $api, array $payload): bool
    {
        $logger = $this->getLogger();

        /* Optionally show array payloads
         */
        if ($this->getOpt(self::OPT_LONG_BLUEPRINT)) {
            $logger->info('Payload ready to send to Learnosity:');
            print_r($payload);
        }

        if ($this->perform()) {
            $logger->info(' * Requesting updates to chunk of ' . count($payload['questions']) . ' questions (using /itembank/questions).');
            $t0 = microtime(true);
            try {
                // Learnosity docs: https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
                $response = $api->dataSetRequest('/itembank/questions', $payload);
            } catch (JsonException) {
                $logger->info('Request failed due to JsonException.');
                return false;
            } catch (ValidationException) {
                $logger->info('Request failed due to ValidationException');
                return false;
            }
            $this->apiUpdatesTotalWaitTimeSeconds += microtime(true) - $t0;

            $status = $response['meta']['status'] ?? false;

            if ($status !== true) {
                $logger->info("Request failed with status '{$response['meta']['status']}'");
                return false;
            }

            $logger->info('   Request succeeded.');
        } else {
            $logger->info(
                'Skipped sending ' . count($payload['questions']) . ' updated questions to Learnosity because not in perform mode.'
            );
        }

        return true;
    }
}
