<?php

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Url\Factory;

/**
 * Attempts can sometimes end up having a max_mark of 0, having been
 * co-erced from null, as a result of SA-18703's tool. This prevents
 * you from altering someone's grade, as zero denominators aren't
 * permitted in our LMS, but are allowed to manifest via learnosity
 * who doesn't mandate questions having a meaningful non-zero value.
 *
 * This divide-by-zero constraint effectively makes grades readonly
 * since validation prevents you from changing grades values.
 *
 * This tool takes an attempt's Learnosity identifier and tries to do
 * a deeper inspection about the attempt, to try and recalculate what
 * the max_mark should have been.
 */
class RecalculateMaxMarkTool extends CliTool\App
{
    /**
     * Help
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --reference
     *
     * Inspect a particular assess_attempt.learnosity_reference to see what
     * max_mark perhaps *should* have been
     */
    private const OPT_LONG_ATTEMPT_REFERENCE = 'reference:';


    /**
     * A learnosity api instance
     *
     * @var Api
     */
    private Api $api;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ATTEMPT_REFERENCE,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Sometimes attempts will have computed a max_score of null which got coerced into 0.00
which prevents teachers from changing grades associated with these attempts.

This tool asks Learnosity about what it knows and tries to update max_mark in our
assess_attempt table for the given learnosity reference.

Note that the reference is that of the *attempt* which you can find in assess_attempt.

Usage: recalculateMaxMarks.php [--reference=...] [-vvv] [--perform]
Ex.    recalculateMaxMarks.php --reference=abc-123-def-456 -vvv --perform

HELP;
            exit;
        }

        /* Log the title first lol
         */
        $logger = $this->getLogger();
        $logger->info('');
        $logger->info('Learnosity Recalculate Max Marks');
        $logger->info('');

        /* Multiple usages of --reference=... will cause OPT_LONG_ATTEMPT_REFERENCE to give
         * you an array of strings, instead of just a string itself, so actively prevent that
         * for now. Later, if there's need for it, add support for multiple --reference values
         * and pump that into the $statement below with
         *
         * WHERE learnosity_reference IN ...
         */
        if (is_array($this->getOpt(self::OPT_LONG_ATTEMPT_REFERENCE))) {
            $this->getLogger()->info('Please provide one attempt reference to inspect; you supplied multiple.');

            return;
        }

        /* Check mandatory args first
         */
        $attemptReference = trim((string)$this->getOpt(self::OPT_LONG_ATTEMPT_REFERENCE));

        if ('' === $attemptReference) {
            $this->getLogger()->info('Please provide an attempt reference to inspect.');

            return;
        }

        /* Try to obtain the attempt
         *
         * Potential upgrade: let this scan multiple/all attempts looking for 0 max_mark
         * rows in our assess_attempt, but that's a pretty big set of changes to touch.
         * Maybe a bit too dangerous?
         */
        $statement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM assess_attempt
            WHERE learnosity_reference = :attemptReference  
            AND deleted_at IS NULL
            LIMIT 1
        ");

        $statement->execute([
            'attemptReference' => $attemptReference
        ]);

        /* There should only be one record here
         */
        $attempt = $statement->fetchAll(\PDO::FETCH_ASSOC)[0] ?? null;

        /* No rows found; at least tell the operator
         */
        if (null === $attempt) {
            $this->getLogger()->info(sprintf(
                'No attempts associated with reference %s',
                $attemptReference
            ));

            return;
        }

        $this->getLogger()->info(sprintf(
            "Attempt %s",
            $attemptReference,
        ));

        /* Build dependencies
         */
        $this->api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get(ConfigMapper::NAME_LEARNOSITY_VERSION),
            $this->getConfig()->get(ConfigMapper::NAME_LEARNOSITY_CONSUMER_KEY),
            $this->getConfig()->get(ConfigMapper::NAME_LEARNOSITY_CONSUMER_SECRET),
            $this->getLogger(),
        );

        /* Ask learnosity what it knows
         */
        $result = $this->api->dataGetRequest('/sessions/scores', [
            'session_id' => [
                $attemptReference
            ]
        ])['data'][0] ?? null;

        /* Compare what we know vs what learnosity knows
         */
        $this->getLogger()->info(sprintf(
            '    Schoolbox attempt max_mark: %s',
            $attempt['max_mark'] ?? '(null)',
        ));

        $this->getLogger()->info(sprintf(
            '    Learnosity session max_score: %s',
            $result['max_score']
        ));

        if ($this->perform()) {
            $this->getLogger()->debug('    Performing update...');

            $this->getDatabaseInternal()->executeQuery("
                UPDATE assess_attempt
                SET max_mark = :maxMark
                WHERE learnosity_reference = :attemptReference
                LIMIT 1
            ", [
                'maxMark' => (float)$result['max_score'],
                'attemptReference' => $attemptReference,
            ]);

            /* Verify outcome
             */
            $statement->execute([
                'attemptReference' => $attemptReference,
            ]);

            $updatedAttempt = $statement->fetchAll(\PDO::FETCH_ASSOC)[0];

            $this->getLogger()->info(sprintf(
                "    Updated attempt to have max_mark of %s",
                $updatedAttempt['max_mark']
            ));
        }

        $this->getLogger()->info('');
    }
}
