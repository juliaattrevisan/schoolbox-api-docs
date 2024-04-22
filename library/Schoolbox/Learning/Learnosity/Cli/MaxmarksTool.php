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
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Learnosity\Codec\UserIdentifier;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Url\Factory;

/**
 * Learnosity Attempt Rescore
 *
 * Causes all attempts of a particular quiz to be rescored by learnosity
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

Short Answer questions have an undocumented field: validation.max_score which can be used
to store the maximum marks available when automark is false. When automark is true, a different
field is used: validation.valid_response.score

This tool copies the one from validation.valid_response.score into validation.max_score so that
its value is everywhere and should support both cases of automark.

Usage: maxmarks.php --assessment=... [-vvv] [--perform]
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
        $api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );


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
