<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Codec\UserIdentifier;
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
     * Import a particular quiz from the schoolbox.assessment table
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';


    /**
     * @var \Schoolbox\Learning\Quiz\Mapper
     */
    private \Schoolbox\Learning\Quiz\Mapper $quizMapper;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT_ID
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

Usage: rescore.php --assessment=... [-vvv]


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
            $this->getLogger()->warning(sprintf(
                'Assessment %s was found to be a survey somehow; exiting',
                $assessment->getId()
            ));

            exit(1);
        }

        /* Build dependencies
         */
        $api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        /* As per https://help.learnosity.com/hc/en-us/requests/24890
         * We have to find all the Item References for the given quiz and collate them into
         * a list, on behalf of learnosity - costing us a round trip api request
         */
        $itemReferences = $api->dataGetRequest('/itembank/activities', [
            'references' => [
                $quiz->getLearnosityReference()
            ]
        ])['data'][0]['data']['items'] ?? null;

        /* No items? No trabajo
         */
        if (null === $itemReferences) {
            $this->getLogger()->warning(sprintf(
                'Assessment %s has no item references in learnosity; exiting',
                $assessment->getId()
            ));

            exit(1);
        }

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

        /* Find all the attempts for this quiz
         */
        $attempts = $attemptMapper->findByColumn('assess_id', $quiz->getId());

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

        /* Tell learnosity about the ones we need to rescore
         */
        $payload = [
            'session_ids' => [],
            'target_item' => []
        ];

        foreach ($attempts as $attempt) {
            $this->getLogger()->info(sprintf(
                'Preparing attempt %s (%s) for rescoring',
                $attempt->getId(),
                $attempt->getLearnosityReference()
            ));

            $payload['session_ids'][] = $attempt->getLearnosityReference();
        }

        /* Tell learnosity about the items among these session(s)
         */
        foreach ($itemReferenceList as $itemReference) {
            $payload['target_item'] = [
                'reference' => $itemReference
            ];

            $this->getLogger()->info('');

            $this->getLogger()->info(sprintf(
                'Sending %s attempt(s) to learnosity for rescoring item %s',
                count($payload['session_ids']),
                $itemReference
            ));

            if ($this->perform()) {
                $response = $api->dataUpdateRequest('/sessions/item', $payload);

                $this->getLogger()->info(sprintf(
                    'Rescoring request made to learnosity and returned job_reference %s',
                    $response['data']['job_reference']
                ));
            }
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
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
    protected function getQuizForAssessmentId(int $assessmentId): Quiz
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS !== $quiz->getAvailability()) {
            throw new \RuntimeException(sprintf(
                'Assessment id %s is somehow not associated with a quiz.',
                $assessmentId
            ));
        }

        return $quiz;
    }
}
