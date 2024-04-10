<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Attempt\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory as UrlFactory;
use Throwable;

class AutoscoreAttemptsTool extends CliTool\App
{
    /**
     * Name of this tool's lock.
     */
    public const LOCK_NAME = 'learnosity_autoscore_attempts';

    /**
     * Lock timeout: 60s I guess?
     */
    public const LOCK_TIMEOUT = 60;

    /**
     * Help (long and short options)
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    /**
     * --assessment
     *
     * Process a particular quiz
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';

    /**
     * --attempt
     *
     * Process a particular quiz attempt
     */
    private const OPT_LONG_ATTEMPT_ID = 'attempt:';

    /**
     * --from
     *
     * Process only attempts created on or after this date.
     */
    private const OPT_LONG_FROM = 'from:';

    /**
     * --to
     *
     * Process only attempts created on or before this date.
     */
    private const OPT_LONG_TO = 'to:';

    /**
     * --process-null-status
     *
     * Process unreviewed null status attempts as well as attempts with
     * processing status: attempts which were created before status was added
     * will by default have a null status.
     */
    private const OPT_LONG_PROCESS_NULL_STATUS = 'process-null-status';

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
     * * --help:                help
     * * --from:                from date
     * * --to:                  to date
     * * --assessment:          a particular quiz to process
     * * --attempt:             a particular attempt to process
     * * --process-null-status: process attempts with status null
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FROM,
            self::OPT_LONG_TO,
            self::OPT_LONG_ASSESSMENT_ID,
            self::OPT_LONG_ATTEMPT_ID,
            self::OPT_LONG_PROCESS_NULL_STATUS,
        ];
    }

    /**
     * Search for quiz attempts which are awaiting an auto-score.
     *
     * Then, query Learnosity for whether they have been scored yet: if so, add
     * the mark and normalised mark from Learnosity.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Lock
        $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TIMEOUT);

        // Set up the necessary helpers and mappers
        $api = new Api(
            (new UrlFactory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );
        $helper = (new Helper($api, $this->getLogger()));

        /** @var AssessmentMapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $attemptMapper        = Mapper::getInstance();

        // Find attempts
        $attempts      = $attemptMapper->findAwaitingAutoscore(
            (bool)($this->getOpt(self::OPT_LONG_PROCESS_NULL_STATUS)),
            $this->getOptAsInt(self::OPT_LONG_ATTEMPT_ID),
            $this->getOptAsInt(self::OPT_LONG_ASSESSMENT_ID),
            $this->buildDate($this->getOpt(self::OPT_LONG_FROM)),
            $this->buildDate($this->getOpt(self::OPT_LONG_TO)),
        );
        if (count($attempts) === 0) {
            $this->getLogger()->notice(sprintf('No quiz attempts require auto-scoring: nothing to do'));
            $lock->release();
            return;
        }
        $this->getLogger()->notice(sprintf('%d quiz attempts require auto-scoring', count($attempts)));
        $quizCache = [];

        // And process them
        foreach ($attempts as $attempt) {
            $lock->refresh(self::LOCK_TIMEOUT);
            $quizId = $attempt->getQuiz()->getAssessmentModel()->getId();
            $this->getLogger()->notice(sprintf('Processing attempt #%d for quiz #%d', $attempt->getId(), $quizId));
            try {
                $quizCache[$quizId] ??= $assessmentDataSource->findByIdForReview($quizId);
            } catch (MapperException $e) {
                $this->getLogger()->warning(
                    sprintf('Quiz #%d not found (error: %s): skipping', $quizId, $e->getMessage()),
                );
                continue;
            }
            if (!$quizCache[$quizId] instanceof QuizObject) {
                $this->getLogger()->warning(
                    sprintf('Assessment with ID #%d is somehow not a quiz: skipping', $quizId),
                );
                continue;
            }
            if ($this->perform()) {
                try {
                    $helper->processAttemptCompletion($attemptMapper, $quizCache[$quizId], $attempt);
                } catch (Throwable $e) {
                    $this->getLogger()->warning(
                        sprintf(
                            'Attempt #%d could not be processed (error: %s): skipping',
                            $attempt->getId(),
                            $e->getMessage(),
                        ),
                    );
                    continue;
                }
                match ($attempt->getLearnosityStatus()) {
                    Helper::LEARNOSITY_PROCESS_STATUS_PENDING => $this->getLogger()->warning(
                        sprintf('Attempt #%d is somehow pending: should be impossible', $attempt->getId()),
                    ),
                    Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING => $this->getLogger()->debug(
                        sprintf('Attempt #%d is still processing', $attempt->getId()),
                    ),
                    Helper::LEARNOSITY_PROCESS_STATUS_COMPLETED => $this->getLogger()->notice(
                        sprintf('Attempt #%d has been processed: scores have been loaded', $attempt->getId()),
                    ),
                    Helper::LEARNOSITY_PROCESS_STATUS_FAILED => $this->getLogger()->warning(
                        sprintf('Attempt #%d has failed to process', $attempt->getId()),
                    ),
                    Helper::LEARNOSITY_PROCESS_STATUS_UNPROCESSABLE => $this->getLogger()->warning(
                        sprintf('Attempt #%d has been determined to be unprocessable', $attempt->getId()),
                    ),
                    default => $this->getLogger()->warning(
                        sprintf(
                            'Attempt #%d has unknown status (%s): should be impossible',
                            $attempt->getId(),
                            $attempt->getLearnosityStatus(),
                        ),
                    ),
                };
            }
        }

        $this->getLogger()->notice('Auto-scoring finished.');
        $lock->release();
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
    Auto-score quiz attempts tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Auto-score quiz attempts which have not yet been scored by Learnosity.

    This will not set the score for any attempts which have been reviewed, but may set their `learnosity_status` field to `completed`.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually auto-score: otherwise, only output attempts which would be scored.

    $yellow--assessment=ASSESSMENT_ID$white
        If provided: only score attempts on the quiz with this assessment ID.

    $yellow--attempt=ATTEMPT_ID$white
        If provided: only score this attempt ID.

    $yellow--from=FROM_DATE$white
        If provided: only score attempts finished from {$yellow}FROM_DATE{$white} onward.

        {$yellow}FROM_DATE{$white} will be parsed according to the following rules:
        * as a database date (YYYY-MM-DD HH:MM:SS)
        * as a Unix timestamp
        * via `strtotime`

        This script will fail if {$yellow}FROM_DATE{$white} is not parseable.

    $yellow--to=TO_DATE$white
        If provided: only score attempts finished on {$yellow}TO_DATE{$white} and before.

        {$yellow}TO_DATE{$white} will be parsed using the same rules as {$yellow}FROM_DATE{$white}, and will cause this script to fail for the same reasons.

    $yellow--process-null-status$white
        If provided, auto-score attempts whose `learnosity_status` field is `NULL` in addition to those `processing`.

        This should only be required for if historic attempts need to be auto-scored.


HELP;
    }
}
