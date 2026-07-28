<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use RuntimeException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;

/**
 * Base class for the tools for transitioning a Learnosity quiz duplication
 * through its states.
 */
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
     * --from
     *
     * Process only quizzes created on or after this date.
     */
    private const OPT_LONG_FROM = 'from:';

    /**
     * --to
     *
     * Process only quizzes created on or before this date.
     */
    private const OPT_LONG_TO = 'to:';
    /**
     * --batch-size
     *
     * Maximum number of quizzes to process per run.
     */
    private const OPT_LONG_BATCH_SIZE = 'batch-size:';

    private const DEFAULT_BATCH_SIZE = 50;
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
     * * --from:       from date
     * * --to:         to date
     * * --assessment: a particular quiz to process
     * * --batch-size: maximum number of quizzes to process
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FROM,
            self::OPT_LONG_TO,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_BATCH_SIZE,
        ];
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

        if ($this->perform()) {
            $this->getLogger()->notice('Running in perform mode (-p): quizzes will be changed.');
        } else {
            $this->getLogger()->notice('Running in planning mode: use -p to commit changes to quizzes.');
        }

        // Firstly, check if the importer is running: if it is, exit.
        // Can't let these tools run whilst the importer is running, because it
        // is possible this tool will attempt to duplicate a quiz which has a
        // Learnosity reference, but its content has not yet synced across.
        try {
            $importerLock = $this->acquireLock(ImporterTool::LOCK_NAME, 1);
        } catch (RuntimeException) {
            $this->getLogger()->warning(
                'Importer lock is held by another process (likely the Learnosity importer): aborting due to possible conflict',
            );
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }
        $importerLock->release();
        unset($importerLock);

        // Also ensure that this tool is not already running:
        // the process _should_ be idempotent, but still would prefer that no
        // more than one is running at once.
        try {
            $toolLock = $this->acquireLock($this->getLockName(), $this->getLockTimeout());
        } catch (RuntimeException) {
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        // Build duplicator
        $baseUrl          = (new Factory($this->getConfig()))->buildFullyQualified();
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
            $this->getLogger(),
        );

        // Build assessment data source
        $batchSize = $this->getOptAsInt(self::OPT_LONG_BATCH_SIZE, self::DEFAULT_BATCH_SIZE);
        if ($batchSize === null || $batchSize <= 0) {
            throw new \InvalidArgumentException('Batch size must be a positive integer.');
        }

        $this->assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        $lastCreatedAt = null;
        $lastAssessId  = null;
        $runningCount = 0;
        $loops = 0;
        do {
            $loops++;
            $result  = $this->findQuizzes($batchSize, $lastCreatedAt, $lastAssessId);
            $quizzes = $result['quizzes'];

            $this->getLogger()->info(sprintf('Found %d quizzes', count($quizzes)));
            foreach ($quizzes as $quiz) {
                // Not a quiz somehow? Skip
                $toolLock->refresh();
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

                $runningCount++;
                /** @noinspection PhpUnhandledExceptionInspection - I want this exception to fall on carpet */
                $this->processQuiz(
                    $quiz
                );
            }

            $lastCreatedAt = $result['lastCreatedAt'];
            $lastAssessId  = $result['lastAssessId'];
            $toolLock->refresh();
        } while ($quizzes->count() === $batchSize);
        $this->getLogger()->info(sprintf('Processed %d quizzes over %d loops', $runningCount, $loops));
        $toolLock->release();
        $this->getLogger()->notice($this->perform() ? 'Quizzes modified.' : 'Planning finished.');
    }

    /**
     * Find quizzes which match this tool's targeted quiz status, and which are
     * also within the date span or assessment ID list.
     *
     * @param int         $batchSize
     * @param string|null $lastCreatedAt
     * @param int|null    $lastAssessId
     * @return array{quizzes: GenericCollection<IAssessable>, lastCreatedAt: string|null, lastAssessId: int|null}
     */
    private function findQuizzes(
        int $batchSize,
        ?string $lastCreatedAt = null,
        ?int $lastAssessId = null,
    ): array {
        $whereParts = [
            "quiz.learnosity_job_status = :learnosityJobStatus
            AND quiz.deleted_at IS NULL", // assessmentDataSource->findAllByIds($quizIds) only returns quizzes which are not deleted, but we want to filter them out here too so that the log output is accurate
        ];
        $params = [
            'learnosityJobStatus' => $this->getTargetedQuizStatus(),
        ];
        $this->getLogger()->info('Filtering quizzes to those which match:');
        $this->getLogger()->info(sprintf('* learnosity_job_status = %s', $params['learnosityJobStatus']));
        $this->getLogger()->info(sprintf('* batch size = %d', $batchSize));

        if ($lastAssessId !== null) {
            if ($lastCreatedAt === null) {
                $whereParts[] = '(
                    quiz.created_at IS NOT NULL
                    OR (quiz.created_at IS NULL AND quiz.assess_id > :lastAssessId)
                )';
            } else {
                $whereParts[] = '(
                    quiz.created_at > :lastCreatedAt
                    OR (
                        quiz.created_at = :lastCreatedAt
                        AND quiz.assess_id > :lastAssessId
                    )
                )';
                $params['lastCreatedAt'] = $lastCreatedAt;
            }
            $params['lastAssessId'] = $lastAssessId;
        }

        $fromDate = $this->buildDate($this->getOpt(self::OPT_LONG_FROM));
        if ($fromDate) {
            $whereParts[] = 'assessment.created_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
            $this->getLogger()->info(
                sprintf('* created >= %s (%s)', $this->getOpt(self::OPT_LONG_FROM), $params['fromDate']),
            );
        }
        $toDate = $this->buildDate($this->getOpt(self::OPT_LONG_TO));
        if ($toDate) {
            $whereParts[] = 'assessment.created_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
            $this->getLogger()->info(
                sprintf('* created <= %s (%s)', $this->getOpt(self::OPT_LONG_TO), $params['toDate']),
            );
        }
        $assessmentId = $this->getOptAsInt(self::OPT_LONG_ASSESSMENT_ID);
        if ($assessmentId) {
            $whereParts[] = 'quiz.assessment_id = :assessmentId';
            $params['assessmentId'] = $assessmentId;
            $this->getLogger()->info(
                sprintf('* assessment_id = %d', $params['assessmentId']),
            );
        }

        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "
                SELECT assessment.id, quiz.created_at, quiz.assess_id
                FROM assess AS quiz
                INNER JOIN assessment ON quiz.assessment_id = assessment.id
                WHERE
            " . implode(' AND ', $whereParts) . "
                ORDER BY quiz.created_at ASC, quiz.assess_id ASC
                LIMIT :batchSize
            ",
            array_merge($params, ['batchSize' => $batchSize])
        );
        $quizQuery->execute();
        $rows    = $quizQuery->fetchAll(\PDO::FETCH_ASSOC);
        $quizIds = array_column($rows, 'id');
        $lastRow = $rows[array_key_last($rows)] ?? null;

        return [
            'quizzes'      => $this->assessmentDataSource->findAllByIds($quizIds),
            'lastCreatedAt' => $lastRow['created_at'] ?? null,
            'lastAssessId'  => isset($lastRow['assess_id']) ? (int)$lastRow['assess_id'] : null,
        ];
    }

    /**
     * Which quiz status does this tool target?
     *
     * @return Helper::LEARNOSITY_PROCESS_STATUS_*
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
     * Retrieve the name of the lock for this tool.
     *
     * @return string
     */
    abstract protected function getLockName(): string;

    /**
     * Retrieve the expected maximum time (in seconds) for a single iteration of
     * `processQuiz`: the lock will be held and refreshed for this amount of
     * time.
     *
     * @return int
     */
    abstract protected function getLockTimeout(): int;

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
        $batchSize  = self::DEFAULT_BATCH_SIZE;
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

    $yellow--assessment=ASSESSMENT_ID$white
        If provided: only perform changes on the quiz with this assessment ID.

    $yellow--from=FROM_DATE$white
        If provided, only perform changes on quizzes created from {$yellow}FROM_DATE{$white} onward.

        {$yellow}FROM_DATE{$white} will be parsed according to the following rules:
        * as a database date (YYYY-MM-DD HH:MM:SS)
        * as a Unix timestamp
        * via `strtotime`

        This script will fail if {$yellow}FROM_DATE{$white} is not parseable.

    $yellow--to=TO_DATE$white
        If provided, only only perform changes on quizzes created on {$yellow}TO_DATE{$white} and before.

        {$yellow}TO_DATE{$white} will be parsed using the same rules as {$yellow}FROM_DATE{$white}, and will cause this script to fail for the same reasons.

    $yellow--batch-size=BATCH_SIZE$white
        If provided, process at most {$yellow}BATCH_SIZE{$white} matching quizzes.
        Defaults to {$yellow}{$batchSize}{$white}.


HELP;
    }
}
