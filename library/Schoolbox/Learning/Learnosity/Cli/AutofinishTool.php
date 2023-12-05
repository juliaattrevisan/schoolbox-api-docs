<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Quiz\Model as Quiz;

/**
 * Learnosity Attempt Autofinisher
 *
 * Automatically (and periodically) shut attempts that should have been manually
 * shut by students, but weren't
 */
class AutofinishTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * Give an extra 5 minutes before we shut these attempts
     * for some historic reason (imho not required)
     */
    private const EXPIRY_OFFSET = -5;


    /**
     * @var \Schoolbox\Learning\Quiz\Mapper
     */
    private \Schoolbox\Learning\Quiz\Mapper $quizMapper;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Auto-finishes quiz attempts which have expired; users sometimes forget to officially
finish their attempts so we do it for them when the quiz expires.

This runs periodically on attempts that exist in Learnosity: these are attempts which
have an assess_attempt.learnosity_reference value. Any legacy attempts are ignored,
finished or otherwise.

Notes:
* This always mutates data; it's always in Perform mode.
* The verbose mode will log more diagnostic information but you shouldn't use it in prod.

Usage: autoFinish.php [-vvv]


HELP;
            exit;
        }

        /* We need these mappers
         */
        $this->quizMapper = \Schoolbox\Learning\Quiz\Mapper::getInstance();
        $userMapper = \Schoolbox\UserMapper::getInstance();

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* Look for quiz attempts that should have expired before this time
         * Potentially parameterise this
         *
         * If you wanted to have this "leeway" idea, you could subtract 5 minutes
         * from this $expiry ADF to give network latency time to resolve itself
         * (I'm not sure what that's about, unfortunately...keeping it for historic reasons)
         */
        $expiry = \Alaress_Date_Format::now()->addMinutes(self::EXPIRY_OFFSET);

        /* Find the assessments which have attempts that need to be shut
         *     - Only process living attempts (ignore deleted attempts)
         *     - ...which haven't already finished
         *     - ...which are associated with living quizzes (ignore deleted assess and assessment rows)
         *     - ...which also have living users (ignore attempts by deleted users)
         *     - ...which can actually expire (ignore quizzes set to Unlimited duration)
         *     - ...which should have already expired, but haven't
         *     - ...which are for Quizzes specifically (ignore survey attempts)
         *     - ...which are in Learnosity itself
         *
         * This won't take into account quizzes that were reviewed but not finished;
         * this would occur only by direct intervention or hideous mistake
         */
        $query = "
            SELECT 
                assess_attempt.attempt_id,
                assess_attempt.user_id,
                assess.assessment_id,
                assess.assess_id
            FROM assess_attempt
            INNER JOIN assess ON assess_attempt.assess_id = assess.assess_id
            INNER JOIN assessment ON assess.assessment_id = assessment.id
            INNER JOIN `user` u ON assess_attempt.user_id = u.id
            WHERE assess_attempt.deleted_at IS NULL
            AND assess_attempt.finish IS NULL
            AND assessment.deleted_at IS NULL
            AND u.deleted_at IS NULL
            AND assess.duration > :unlimitedDuration
            AND TIMESTAMPADD(SECOND, `assess`.`duration`, `assess_attempt`.`start`) < :expiry
            AND assess_attempt.learnosity_reference IS NOT NULL
            ORDER BY assess.assessment_id, assess_attempt.user_id
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query);

        $statement->execute([
            'unlimitedDuration' => \Schoolbox\Learning\Assessment\Quiz\Model::DURATION_UNLIMITED,
            'expiry' => $expiry->getDatabase()
        ]);

        /* Process each assessment en masse like we usually do
         */
        $lastSeen = [
            'assessmentId' => null,
            'userId' => null
        ];

        /* Total number of attempts we changed
         */
        $totalChanged = 0;

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Attempt Auto-finisher.');
        $this->getLogger()->info('');

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $current = [
                'assessmentId' => $row['assessment_id'],
                'userId' => $row['user_id']
            ];

            /* If the last (assessment, user_id) tuple is null,
             * this is our first iteration: hydrate $assessment and $quiz,
             * then shut this user's attempts en masse
             *
             * or...
             *
             * If the current (assessment, user_id) tuple is different
             * to the previous $lastSeen tuple, we're looking at a whole new person
             * hydrate $assessment and $quiz, then shut this user's attempts en masse
             */
            if ($current !== $lastSeen) {
                $user = $userMapper->findById($row['user_id']);
                $assessment = $assessmentMapperProxy->findByIdForReview($row['assessment_id'], $user);

                /* We need this $quiz container thing to hold attempts
                 */
                try {
                    $quiz = $this->getQuizForAssessmentId($assessment->getId());
                } catch (\RuntimeException $e) {
                    /* This somehow found to be a survey, not a quiz.
                     * How this could be possible is a mystery except due to dev mistake
                     */
                    $this->getLogger()->warning(sprintf(
                        'Assessment %s was found to be a survey somehow; skipping',
                        $assessment->getId()
                    ));

                    continue;
                }

                /* Apparently this is a thing
                 */
                $quiz = $this->quizMapper->loadAttemptsAndAnswers($quiz, $user);

                /* Shut them all
                 */
                $numChanged = $this->quizMapper->closeExpiredAttempts($assessment, $quiz, $expiry);

                /* Summate overall number of changes everywhere
                 */
                $totalChanged += $numChanged;

                /* Tell what we did
                 */
                $this->getLogger()->info(sprintf(
                    'Finished %s attempts by user %s in assessment %s',
                    $numChanged,
                    $user->getId(),
                    $assessment->getId()
                ));

                /* For diagnostics
                 */
                $this->getLogger()->debug(sprintf(
                    'Artificially finished %s attempts by %s (%s) in assessment "%s" (%s), assess_id %s',
                    $numChanged,
                    $user->formatName(),
                    $user->getId(),
                    $assessment->getTitle(),
                    $assessment->getId(),
                    $quiz->getId()
                ));

                /* "Remember us." as simple an order as a king can give
                 */
                $lastSeen = [
                    'assessmentId' => $row['assessment_id'],
                    'userId' => $row['user_id']
                ];
            }

            /* else noop
             *
             * If the current (assessment, user_id) tuple is the same
             * as the previous $lastSeen tuple, we've already done their attempts;
             * keep going (this happens with multiple unfinished attempts by same user;
             * an unintentional edge case caused by broken data, not normal usage)
             */
        }

        $this->getLogger()->info(sprintf(
            'Closed %s attempts.',
            $totalChanged
        ));
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
