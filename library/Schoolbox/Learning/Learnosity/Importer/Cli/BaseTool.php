<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;

abstract class BaseTool extends CliTool\App
{
    /**
     * -h, --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * --assessment
     *
     * Process a particular quiz
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';

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
     * * --help:       help
     * * --assessment: a particular quiz to process
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_ASSESSMENT_ID];
    }

    /**
     * Duplicator for quiz data.
     */
    protected Api\Duplicator $duplicator;

    /**
     * Data source for assessments.
     */
    protected MapperProxy $assessmentDataSource;

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
            new TagFactory($this->getConfig()->get('site_school_code')),
        );

        // Build assessment data source
        $this->assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $quizzes                    = $this->findQuizzes();

        $this->getLogger()->info(sprintf('Found %d quizzes', count($quizzes)));
        foreach ($quizzes as $quiz) {
            // Not a quiz somehow? Skip
            if (!$quiz instanceof QuizDomainModel) {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d (%s) is not a quiz: skipping',
                        $quiz->getTitle(),
                        $quiz->getId(),
                    )
                );
                return;
            }

            /** @noinspection PhpUnhandledExceptionInspection - I want this exception to fall on carpet */
            $this->processQuiz($quiz);
        }
    }

    /**
     * Find quizzes which match this tool's targeted quiz status, and which are
     * also within the date span or assessment ID list.
     *
     * @return GenericCollection<IAssessable>
     */
    private function findQuizzes(): GenericCollection
    {
        $logMessageParts = [
            'learnosity_job_status: ' . $this->getTargetedQuizStatus(),
        ];
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => $this->getTargetedQuizStatus()]);
        $quizIds = $quizQuery->fetchColumnArray('assessment_id');
        return $this->assessmentDataSource->findAllByIds($quizIds);
    }

    /**
     * Which quiz status does this tool target?
     *
     * @return QuizDomainModel::LEARNOSITY_JOB_STATUS_*
     */
    abstract protected function getTargetedQuizStatus(): string;

    /**
     * Process the quiz, in whichever way this tool processes quizzes.
     *
     * @param QuizDomainModel $quiz
     * @return void
     * @throws MapperException if `$quiz` couldn't be saved
     */
    abstract protected function processQuiz(QuizDomainModel $quiz): void;

    /**
     * Print the name of the tool in the help text.
     *
     * @return string
     */
    abstract protected function getToolName(): string;

    /**
     * Print the help text's description.
     *
     * @return string
     */
    abstract protected function getToolDescription(): string;

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
    {$this->getToolName()}

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
{$this->getToolDescription()}

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually perform changes on quizzes: otherwise, only output which quizzes would be changed.


HELP;
    }
}
