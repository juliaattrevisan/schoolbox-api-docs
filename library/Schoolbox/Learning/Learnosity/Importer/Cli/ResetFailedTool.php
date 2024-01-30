<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Interop\Queue\Exception as InteropQueueException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;

/**
 * CLI tool for resetting sync of Learnosity data for quizzes in the failed
 * status.
 *
 * This will set their status back to pending, and clear their job reference.
 */
class ResetFailedTool extends CliTool\App
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
     * Data source for assessments.
     */
    private MapperProxy $assessmentDataSource;

    /**
     * @inheritdoc
     */
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
        );

        // Build assessment data source
        $this->assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        // Find pending quiz domain models
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => QuizDomainModel::LEARNOSITY_JOB_STATUS_FAILED]);
        $quizIds = $quizQuery->fetchColumnArray('assessment_id');
        $failedQuizzes = $this->assessmentDataSource->findAllByIds($quizIds);

        $this->getLogger()->info(sprintf('Found %d quizzes currently failed', count($failedQuizzes)));
        foreach ($failedQuizzes as $failedQuiz) {
            // Not a quiz somehow? Skip
            if (!$failedQuiz instanceof QuizDomainModel) {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d (%s) is not a quiz: skipping',
                        $failedQuiz->getTitle(),
                        $failedQuiz->getId(),
                    )
                );
                continue;
            }

            $this->getLogger()->info(
                sprintf(
                    'Resetting assessment #%d (%s)',
                    $failedQuiz->getId(),
                    $failedQuiz->getTitle(),
                )
            );
            if ($this->perform()) {
                $failedQuiz
                    ->setLearnosityJobStatus(QuizDomainModel::LEARNOSITY_JOB_STATUS_PENDING)
                    ->setLearnosityJobReference(null);
                $this->assessmentDataSource->save($failedQuiz, $failedQuiz->getCreator());
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
    Reset failed quiz duplication tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Resets any failed quiz duplication jobs.

    These jobs will be set back to the pending status, and their job reference will be cleared;
    note this does not address any possible underlying cause for the failure.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually reset failed duplication jobs: otherwise, only output what would be reset.


HELP;
    }
}
