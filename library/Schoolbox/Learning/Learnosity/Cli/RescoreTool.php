<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Url\Factory;

/**
 * Learnosity Attempt Rescore
 *
 * Causes all attempts of a particular quiz to be rescored by learnosity
 */
class RescoreTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * --assessment
     *
     * Update attempts from a particular quiz from the schoolbox.assessment table
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';


    /**
     * --lookahead
     *
     * Scout ahead to see how many attempts and items would be mutated, roughly
     */
    private const OPT_LONG_LOOKAHEAD = 'lookahead';


    /**
     * Roughly how long each learnosioty api requests costs, in seconds
     */
    private const AVERAGE_API_REQUEST_DURATION = 2;


    /**
     * A coefficient applied to a set of item responses that calculates how long
     * it takes to update that set of item responses. Each item costs AVERAGE_API_REQUEST_DURATION
     * plus a rate limit penalty (about 5sec) roughly three in four requests.
     *
     * penalty = 2 + (5 * 0.75) = 3.75
     */
    private const UPDATE_PENALTY = 3.75;


    /**
     * A learnosity api instance
     *
     * @var Api
     */
    private Api $api;


    /**
     * @var \Schoolbox\Learning\Quiz\Mapper
     */
    private \Schoolbox\Learning\Quiz\Mapper $quizMapper;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_LOOKAHEAD,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Short Answer questions didn't have a Points attributed to them, due to a mistake I made
in the initial import procedure. This is rectified by ticking Enable Auto Scoring in the
learnosity user interface, but any attempts that were made and tallied *without* the
Points value will still be stuck like that: they need to be rescored/retallied via this
tool, which lets you specify a particular quiz whose attempts you want to update.

It uses an assessment's learnosity_reference for a quiz whose attempts you want to rescore.

Usage: rescore.php --assessment=... [--lookahead] [-vvv] [--perform]
Ex.    rescore.php --assessment=656 -vvv --perform


Lookahead Mode: you can specify the --lookahead arg which specifically elucidates how many
attempts and items would be updated. It works exclusively; if you specify this arg, this
tool will not perform any mutations, just tell you what it found. You should use this when
you're running Rescore for the first time, to get an idea of what's to come.

Ex.    rescore.php --assessment=656 -vvv --lookahead

HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Attempt Rescorer.');
        $this->getLogger()->info('');

        /* Check mandatory args first
         */
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT_ID));

        if ('' === $assessmentId) {
            $this->getLogger()->info('Please specify an assessment id using --assessment');

            exit;
        }

        /* See if this assessment exists
         */
        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /** @var QuizDomainModel $assessment */
        $assessment = $assessmentMapperProxy->findById($assessmentId);

        /* We need these mappers
         */
        $this->quizMapper = \Schoolbox\Learning\Quiz\Mapper::getInstance();
        $attemptMapper = \Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance();

        /* We need this $quiz container thing to hold attempts
         */
        try {
            $quiz = $this->getQuizForAssessmentId($assessment->getId());
        } catch (\RuntimeException $e) {
            /* This somehow found to be a survey, not a quiz.
             * How this could be possible is a mystery except due to dev mistake
             */
            $this->getLogger()->warning($e->getMessage());

            exit(1);
        }

        /* Build dependencies
         */
        $this->api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        /* Find all the finished attempts for this quiz
         * (Don't try to rescore attempts currently in progress)
         */
        $pb = $attemptMapper->getPredicateBuilder()
            ->equals('assess_id', $quiz->getId())
            ->notNull('finish');

        $attempts = $attemptMapper->findByPredicates($pb);

        /* No attempts? No trabajo
         */
        if (0 === $attempts->count()) {
            $this->getLogger()->info(sprintf(
                'No attempts found for %s (assessmentId: %s, assess_id: %s) - exiting.',
                $assessment->getTitle(),
                $assessment->getId(),
                $quiz->getId()
            ));

            exit(1);
        }

        /* Choose whether we're scouting, or actually gonna do the thing
         */
        if ($this->getOpt(self::OPT_LONG_LOOKAHEAD)) {
            $this->lookahead($attempts);
        } else {
            /* Metrics: announce how many attempts we have, and how many things we're going to touch
             */
            $this->getLogger()->info(sprintf(
                'Found %s attempt(s) to process.',
                $attempts->count()
            ));

            $this->process($attempts);

            $this->getLogger()->info('');
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }


    /**
     * Mutate attempts in learnosity
     *
     * @param GenericCollection $attempts
     * @return void
     * @throws ApiException
     * @throws \JsonException
     * @throws ValidationException
     */
    private function lookahead(\Schoolbox\GenericCollection $attempts): void
    {
        /* A counter for attempts
         */
        $numAttempts = $attempts->count();
        $numSkipped = 0;
        $numItemResponses = 0;

        /* A set of (attempt, num item updates) tuples so you know roughly
         * how many item update requests are required for each attempt
         */
        $breakdown = [];

        foreach ($attempts as $attempt) {
            /* Legacy attempts might not have a learnosity reference;
             * skip those and keep going (this happens in vagrants)
             */
            if ('' === trim((string)$attempt->getLearnosityReference())) {
                $numSkipped++;
                continue;
            }

            /* Find the learnosity Responses for this $attempt as each attempt's
             * contents might actually vary between users
             *
             * This can happen when your item list isn't the same across Sessions
             * such as when you add or remove questions, and students' attempt
             * consequently vary in their content
             *
             * Such a circumstance gives you http 400 "Check session_ids - target_item not used: b985ec0c....."
             * but there's nothing we can do about that
             */
            $itemResponses = $this->api->dataGetRequest('/sessions/responses', [
                'session_id' => [
                    $attempt->getLearnosityReference()
                ]
            ])['data'][0]['items'] ?? null;

            /* No items? No trabajo
             */
            if (null === $itemResponses) {
                $numSkipped++;
                continue;
            }

            /* A counter for items in this attempt
             */
            $n = count($itemResponses);
            $numItemResponses += $n;

            /* Store some more details here
             */
            $key1 = sprintf(
                '%s (%s)',
                $attempt->getUser()->getFullname(),
                $attempt->getUser()->getId()
            );

            $key2 = sprintf(
                'Attempt %s: %s',
                $attempt->getId(),
                $attempt->getLearnosityReference()
            );

            $breakdown[$key1][$key2] = $n;
        }

        /* Log metrics, I guess
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Lookahead Summary');
        $this->getLogger()->info('');

        /* Calculate an estimated duration
         *
         * Each request to learnosity takes about 2 seconds in a vagrant,
         * but updating to the Australia region incurs a Rate Limit penalty, usually ~5sec
         * about 75% of the time
         *
         * eta = 2*numAttempts + 2*numItemResponses + 5*0.75*numItemResponses
         *     = 2*numAttempts + 3.75*numItemResponses
         */
        $eta = (self::AVERAGE_API_REQUEST_DURATION*$numAttempts) + (self::UPDATE_PENALTY*$numItemResponses);

        $this->getLogger()->info(sprintf(
            '    Estimated duration: %s seconds',
            $eta
        ));

        $this->getLogger()->info('');

        $this->getLogger()->info(sprintf(
            '    Number of attempts found: %s',
            $numAttempts
        ));

        $this->getLogger()->info(sprintf(
            '    Number of attempts skipped: %s',
            $numSkipped
        ));

        $this->getLogger()->info(sprintf(
            '    Number of items found: %s',
            $numItemResponses
        ));

        $this->getLogger()->info('');

        /* Brutally dump some per-user stats
         */
        print_r($breakdown);
    }


    /**
     * Mutate attempts in learnosity
     *
     * @param GenericCollection $attempts
     * @return void
     * @throws ApiException
     * @throws \JsonException
     * @throws ValidationException
     */
    private function process(\Schoolbox\GenericCollection $attempts): void
    {
        /* A counter for attempts
         */
        $numAttempts = $attempts->count();
        $i = 0;

        foreach ($attempts as $attempt) {
            $i++;

            $this->getLogger()->info('');

            /* Legacy attempts might not have a learnosity reference;
             * skip those and keep going (this happens in vagrants)
             */
            if ('' === trim((string)$attempt->getLearnosityReference())) {
                $this->getLogger()->info(sprintf(
                    '(%s/%s) Attempt %s does not have a learnosity reference; skipping',
                    $i,
                    $numAttempts,
                    $attempt->getId()
                ));

                continue;
            }

            $this->getLogger()->info(sprintf(
                '(%s/%s) Preparing attempt %s (%s) for rescoring',
                $i,
                $numAttempts,
                $attempt->getId(),
                $attempt->getLearnosityReference()
            ));

            /* Find the learnosity Responses for this $attempt as each attempt's
             * contents might actually vary between users
             *
             * This can happen when your item list isn't the same across Sessions
             * such as when you add or remove questions, and students' attempt
             * consequently vary in their content
             *
             * Such a circumstance gives you http 400 "Check session_ids - target_item not used: b985ec0c....."
             * but there's nothing we can do about that
             */
            $itemResponses = $this->api->dataGetRequest('/sessions/responses', [
                'session_id' => [
                    $attempt->getLearnosityReference()
                ]
            ])['data'][0]['items'] ?? null;

            /* No items? No trabajo
             */
            if (null === $itemResponses) {
                $this->getLogger()->info(sprintf(
                    '(%s/%s) No items found for attempt %s; skipping',
                    $i,
                    $numAttempts,
                    $attempt->getLearnosityReference(),
                ));

                continue;
            }

            /* Collate a set of item references for this attempt
             * Tell learnosity about the ones we need to rescore
             */
            $this->getLogger()->info(sprintf(
                '(%s/%s) Sending %s item(s) to learnosity for rescoring',
                $i,
                $numAttempts,
                count($itemResponses)
            ));

            /* A counter for items in this attempt
             */
            $numItemResponses = count($itemResponses);
            $j = 0;

            foreach ($itemResponses as $itemResponse) {
                $j++;

                $payload = [
                    'session_ids' => [
                        $attempt->getLearnosityReference()
                    ],
                    'target_item' => [
                        'reference' => $itemResponse['reference']
                    ]
                ];

                $this->getLogger()->debug(sprintf(
                    '(%s/%s) [%s/%s] Sending item %s to learnosity for rescoring',
                    $i,
                    $numAttempts,
                    $j,
                    $numItemResponses,
                    $itemResponse['reference']
                ));

                /* Send it to learnosity
                 */
                if ($this->perform()) {
                    try {
                        $job = $this->api->dataUpdateRequest('/sessions/item', $payload);

                        $this->getLogger()->info(sprintf(
                            '(%s/%s) [%s/%s] Rescoring request made to learnosity and returned job_reference %s for item %s',
                            $i,
                            $numAttempts,
                            $j,
                            $numItemResponses,
                            $job['data']['job_reference'],
                            $itemResponse['reference']
                        ));
                    } catch (ApiException $e) {
                        $this->getLogger()->warning($e->getMessage());
                    }
                }
            }
        }
    }


    /**
     * Converts an assessment_id to a Quiz
     *
     * Literally copypasta'd
     *
     * @param int $assessmentId
     * @return Quiz
     * @throws \RuntimeException
     */
    private function getQuizForAssessmentId(int $assessmentId): Quiz
    {
        /** @var null|Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        /* Not a quiz
         */
        if (null === $quiz) {
            throw new \RuntimeException(sprintf(
                'Assessment id %s is not a quiz.',
                $assessmentId
            ));
        }

        if (Quiz::AVAILABILITY_ASSESS !== $quiz->getAvailability()) {
            throw new \RuntimeException(sprintf(
                'Assessment id %s is somehow not associated with a quiz.',
                $assessmentId
            ));
        }

        return $quiz;
    }
}
