<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use JsonException;
use LearnositySdk\Exceptions\ValidationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api as LearnosityApi;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Url\Factory;

/**
 * Learnosity MaxMark Copier
 *
 * Copies the value of validation.valid_response.score into validation.max_score
 * because learnosity has two different contexts of automark: true and false,
 * each setting uses a different field to assign the maximum marks available.
 */
class MaxMarksToolBulk extends CliTool\App
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
     * Maximum number of Questions per payload
     * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
     */
    private const LEARNOSITY_MAX_QUESTIONS = 50;

    /**
     * The number of assessments to process between progress updates.
     * (Progress updates show fraction of assessments processed, and so allow estimation of script completion time.)
     */
    private const N_ASSESSMENTS_BETWEEN_PROGRESS_UPDATES = 10;

    /**
     * @var QuizMapper
     */
    private QuizMapper $quizMapper;

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
                WHERE assm.deleted_at IS NULL
                LIMIT 10'
            )->fetchColumnArray('id');
        } else {
            $assessmentIds = [$assessmentId];
        }

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

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        try {
            $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            $logger->error('Could not get assessment data source.  ' . $e->getMessage());
            exit(1);
        }

        /* We need these mappers
         */
        $this->quizMapper = QuizMapper::getInstance();

        $n = 0; // Loop counter.
        foreach ($assessmentIds as $assessmentId) {
            $logger->debug('Assessment ' . ++$n . " of $nAssessmentIds (assessmentId $assessmentId)");

            /* See if this assessment exists
             */
            /** @var QuizDomainModel $assessment */
            try {
                $assessment = $assessmentMapperProxy->findById($assessmentId);
            } catch (Deleted) {
                $logger->warning('Skipping deleted assessment.');
                continue;
            } catch (Exception) {
                $logger->warning('Could not get assessment.  Skipping.');
                continue; // Skip deleted assessments.
            }

            /* We need this $quiz container thing to hold attempts
             */
            try {
                $quiz = $this->getQuizForAssessmentId($assessment->getId());
            } catch (RuntimeException $e) {
                /* This somehow found to be a survey, not a quiz.
                 * How this could be possible is a mystery except due to dev mistake
                 */
                $logger->error('Could not get quiz container object.  ' . $e->getMessage());
                $logger->info('Continuing to next assessment.');
                exit(1);
            }

            /* Check this quiz is in learnosity
             */
            if ('' === trim((string)$quiz->getLearnosityReference())) {
                $logger->info(sprintf(
                    'Assessment %s (assess_id %s) does not have a learnosity reference; exiting.',
                    $assessment->getId(),
                    $quiz->getId()
                ));
                $logger->info('Continuing to next assessment.');
                continue;
            }

            $itemReferences = null;

            try {
                $itemReferences = $api->dataGetRequest('/itembank/activities', [
                    'references' => [
                        $quiz->getLearnosityReference()
                    ]
                ])['data'][0]['data']['items'];
            } catch (\Exception $e) {
                $logger->error('Request to /itembank/activities failed.  ' . $e->getMessage());
                $logger->info('Continuing to next assessment.');
                continue;
            }

            $itemReferenceList = [];

            if (null !== $itemReferences) {
                /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
                 * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
                 */
                if (is_array($itemReferences[0])) {
                    /* Forcibly mutate this, because learnosity insanity
                     */
                    $itemReferenceList = array_column($itemReferences, 'id');
                } else {
                    /* Presume it was a list of reference strings already
                     */
                    $itemReferenceList = $itemReferences;
                }
            }

            /* Get the full struct of data from learnosity, because they can't cope with partial data
             */
            try {
                $items = $api->dataGetRequest('/itembank/items', ['references' => $itemReferenceList]);
            } catch (JsonException | ValidationException $e) {
                $logger->error('Request to /itembank/items failed.  ' . $e->getMessage());
                $logger->info('Continuing to next assessment.');
                continue;
            }

            foreach ($items['data'] as $item) {
                $itemTitleLogged = false;
                foreach ($item['questions'] as $questionStub) {
                    if (self::LEARNOSITY_SHORT_TEXT === $questionStub['type']) {
                        if (!$itemTitleLogged) {
                            // Only log the item title if the item contains quiz questions.
                            $logger->debug(" * Item '{$item['title']}':");
                            $itemTitleLogged = true;
                        }

                        $question = null;

                        try {
                            $question = $api->dataGetRequest(
                                '/itembank/questions',
                                ['references' => [$questionStub['reference']]]
                            )['data'][0];
                        } catch (JsonException | ValidationException $e) {
                            $logger->error('   Request to /itembank/questions failed.  ' . $e->getMessage());
                            $logger->debug('   Continuing to next question.');
                            continue;
                        }

                        /* If the max_score value was already set, don't change anything;
                         * the user might have done something here.
                         */
                        if (isset($question['data']['validation']['max_score'])) {
                            $logger->debug("    * Question {$question['reference']} - Max score already set - skipping.");
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

                        $logger->debug(sprintf(
                            '    * Question %s - Copying validation.valid_response.score %s to validation.max_score.',
                            $question['reference'],
                            $updatedQuestion['data']['validation']['valid_response']['score']
                        ));

                        $payload['questions'][] = $updatedQuestion;

                        if (count($payload['questions']) === self::LEARNOSITY_MAX_QUESTIONS) {
                            // Maximum number of questions for API call reached.
                            if (!$this->sendRequestToLearnosityIfPerforming($api, $payload)) {
                                $logger->info('Request to Learnosity failed, so stopping.');
                                die;
                            }
                            $logger->info(''); // Blank line to make log readable.
                            $payload['questions'] = []; // Clear questions array, so we can continue using $payload for future requests.
                        }
                    }
                }
            }

            if ($n % self::N_ASSESSMENTS_BETWEEN_PROGRESS_UPDATES === 0) {
                $logger->info("--- Processed $n of $nAssessmentIds assessments ---");
            }
        }

        // If there are any updated questions that have not been sent to Learnosity, send them.
        if (count($payload['questions']) > 0) {
            $logger->info(''); // Blank line to make log readable.
            $logger->info(count($payload['questions']) . ' question(s) remain to be dealt with.');
            if (!$this->sendRequestToLearnosityIfPerforming($api, $payload)) {
                $logger->info('Request to Learnosity failed, so stopping.');
            }
        }

        $logger->info('');
        $logger->info("Processed all $n assessments.  Done.");
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

        $logger->info(''); // Blank line to make log readable.

        /* Optionally show array payloads
         */
        if ($this->getOpt(self::OPT_LONG_BLUEPRINT)) {
            $logger->info('Payload ready to send to Learnosity:');
            print_r($payload);
        }

        if ($this->perform()) {
            $logger->info('Sending ' . count($payload['questions']) . ' updated questions to Learnosity...');

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

            $status = $response['meta']['status'] ?? false;

            if ($status !== true) {
                $logger->info("Request failed with status '{$response['meta']['status']}'");
                return false;
            }

            $logger->info('Request succeeded.');
        } else {
            $logger->info(
                'Skipped sending ' . count($payload['questions']) . ' updated questions to Learnosity because not in perform mode.'
            );
        }

        return true;
    }

    /**
     * Converts an assessment_id to a Quiz
     *
     * Literally copypasta'd
     *
     * @param int $assessmentId
     * @return Quiz
     * @throws RuntimeException
     */
    protected function getQuizForAssessmentId(int $assessmentId): Quiz
    {
        /** @var null|Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        /* Not a quiz
         */
        if (null === $quiz) {
            throw new RuntimeException(sprintf(
                'Assessment id %s is not a quiz.',
                $assessmentId
            ));
        }

        if (Quiz::AVAILABILITY_ASSESS !== $quiz->getAvailability()) {
            throw new RuntimeException(sprintf(
                'Assessment id %s is somehow not associated with a quiz.',
                $assessmentId
            ));
        }

        return $quiz;
    }
}
