<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Url\Factory;

/**
 * CLI tool for polling quizzes in the processing status.
 */
class PollProcessingTool extends CliTool\App
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * Short options:
     * * -h: help
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * Long options:
     * * --help:  help
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * Duplicator for quiz data.
     */
    private Api\Duplicator $duplicator;

    /**
     * Data source for assessments.
     */
    private MapperProxy $assessmentDataSource;

    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $baseUrl = (new Factory($this->getConfig()))->buildFullyQualified();

        // Build duplicator
        $this->duplicator = new Api\Duplicator(
            new Api(
                $baseUrl->getHost(),
                $this->getConfig()->get('learnosity_version'),
                $this->getConfig()->get('learnosity_consumer_key'),
                $this->getConfig()->get('learnosity_consumer_secret'),
                $this->getLogger(),
            ),
            $this->getDatabaseInternal(),
            $baseUrl,
            $this->getDi()->get('httpFactory'),
            $this->getDi()->get('router.generator'),
            $this->getDi()->get('taskd.httpRequestMessageFactory'),
            $this->getDi()->get('taskd.messageProducer'),
            new TagFactory($this->getConfig()->get('site_school_code')),
        );

        // Build assessment data source
        $this->assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        // Find pending quiz domain models
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => QuizDomainModel::LEARNOSITY_JOB_STATUS_PROCESSING]);
        $quizIds = $quizQuery->fetchColumnArray('assessment_id');
        $processingQuizzes = $this->assessmentDataSource->findAllByIds($quizIds);

        $this->getLogger()->info(sprintf('Found %d quizzes currently processing', count($processingQuizzes)));
        foreach ($processingQuizzes as $processingQuiz) {
            // Not a quiz somehow? Skip
            if (!$processingQuiz instanceof QuizDomainModel) {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d (%s) is not a quiz: skipping',
                        $processingQuiz->getTitle(),
                        $processingQuiz->getId(),
                    )
                );
                continue;
            }

            $this->getLogger()->info(
                sprintf(
                    'Force-polling assessment #%d (%s)',
                    $processingQuiz->getId(),
                    $processingQuiz->getTitle(),
                )
            );

            if ($this->perform()) {
                $this->duplicator->pollQuizStatus($processingQuiz);
                $newStatus = $processingQuiz->getLearnosityJobStatus();
                match ($newStatus) {
                    QuizDomainModel::LEARNOSITY_JOB_STATUS_COMPLETED => $this->getLogger()->info(
                        sprintf('Assessment #%d successfully processed', $processingQuiz->getId())
                    ),
                    QuizDomainModel::LEARNOSITY_JOB_STATUS_PROCESSING => $this->getLogger()->info(
                        sprintf('Assessment #%d still processing', $processingQuiz->getId())
                    ),
                    QuizDomainModel::LEARNOSITY_JOB_STATUS_FAILED => $this->getLogger()->warning(
                        sprintf('Assessment #%d processing failed', $processingQuiz->getId())
                    ),
                    QuizDomainModel::LEARNOSITY_JOB_STATUS_PENDING => $this->getLogger()->warning(
                        sprintf('Assessment #%d somehow pending: should be impossible', $processingQuiz->getId())
                    ),
                    default => $this->getLogger()->warning(
                        sprintf(
                            'Assessment #%d unknown status (%s): should be impossible',
                            $processingQuiz->getId(),
                            $newStatus,
                        )
                    ),
                };
            }
        }
    }

    /**
     * Print help text
     *
     * @return void
     */
    private function printHelp(): void
    {
        $file       = $this->getToolFilename();
        $green      = self::COLOUR_GREEN;
        $white      = self::COLOUR_WHITE;
        $yellow     = self::COLOUR_YELLOW;
        echo <<<HELP
NAME:
    Poll processing quiz duplication tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Poll any quiz duplication jobs currently processing.

    This tool will manually poll Learnosity for the status of the quiz's duplication job:
    * if it is finished, it will mark the quiz as complete, and set its tags
    * if it has an error, it will mark it as failed
    * if it hasn't finished yet, it will not be changed

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually poll duplication jobs: otherwise, only output what would be polled.


HELP;
    }
}
