<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Url\Factory;

/**
 * Learnosity MaxMark Copier
 *
 * Copies the value of validation.valid_response.score into validation.max_score
 * because learnosity has two different contexts of automark: true and false,
 * each setting uses a different field to assign the maximum marks available.
 */
class MaxmarksTool extends CliTool\App
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
     * This is listed in a class const, but that class is being altered in another pull request atm
     */
    private const LEARNOSITY_SHORT_TEXT = 'shorttext';


    /**
     * --blueprint
     *
     * Show the payload we intend to send to learnosity land
     */
    private const OPT_LONG_BLUEPRINT = 'blueprint';


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

You can use the --blueprint arg to also show the payload we intend to send to learnosity.

Usage: maxmarks.php --assessment=... [-vvv] [--blueprint] [--perform]
Ex.    maxmarks.php --assessment=656 -vvv --perform

HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Attempt MaxMarks copier.');
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

        /* Check this quiz is in learnosity
         */
        if ('' === trim((string)$quiz->getLearnosityReference())) {
            $this->getLogger()->info(sprintf(
                'Assessment %s (assess_id %s) does not have a learnosity reference; exiting',
                $assessment->getId(),
                $quiz->getId()
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

        /* Used to segregate each school's data; used in tags and author identifiers
         */
        $schoolCode = $this->getConfig()->get('site_school_code');

        $itemReferences = $api->dataGetRequest('/itembank/activities', [
            'references' => [
                $quiz->getLearnosityReference()
            ]
        ])['data'][0]['data']['items'];

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

        /* Get the full struct of data from learnosity, because they can't cope wtih partial data
         */
        $items = $api->dataGetRequest('/itembank/items', [
            'references' => $itemReferenceList
        ]);

        foreach ($items['data'] as $item) {
            foreach ($item['questions'] as $questionStub) {
                if (self::LEARNOSITY_SHORT_TEXT === $questionStub['type']) {
                    $question = $api->dataGetRequest('/itembank/questions', [
                        'references' => [
                            $questionStub['reference']
                        ]
                    ])['data'][0];

                    /* Assign max_score to be the same as validation.valid_response.score
                     * ...because learnosity reasons
                     */
                    $updatedQuestion = $question;

                    /* Short Text doesn't have a max_score field officially, so let's find out
                     * if that's just a mistake, or if it really doesn't.
                     */
                    $updatedQuestion['data']['validation']['max_score'] = $updatedQuestion['data']['validation']['valid_response']['score'];

                    $payload = [
                        'meta' => [
                            'user' => [
                                /* Just assume a system user
                                 *  This is only ever used in learnosity land in some secret place
                                 */
                                'id' => sprintf('%s_SA-17681', $schoolCode)
                            ]
                        ],
                        'questions' => [
                            $updatedQuestion
                        ]
                    ];

                    /* Optionally show array payloads
                     */
                    if ($this->getOpt(self::OPT_LONG_BLUEPRINT)) {
                        print_r($payload);
                    }

                    /* Update it...allegedly
                     */
                    if ($this->perform()) {
                        $this->getLogger()->info(sprintf(
                            'Copying validation.valid_response.score %s to validation.max_score',
                            $updatedQuestion['data']['validation']['valid_response']['score']
                        ));

                        $api->dataSetRequest('/itembank/questions', $payload);
                    }
                }
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
