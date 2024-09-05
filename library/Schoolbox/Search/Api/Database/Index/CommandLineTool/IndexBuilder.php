<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index\CommandLineTool;

use Alaress\Database\Exception;
use Alaress\Lock\LockRefreshException;
use InvalidArgumentException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\Search\Api\Database\Index\CompositeBuilder;
use Schoolbox\Search\Api\Database\Index\IBuilder;
use Schoolbox\Search\Api\Database\Index\Persistor;
use Schoolbox\Search\Api\Database\ResultType;

/**
 * Command-line tool for rebuilding search indices.
 */
class IndexBuilder extends App
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';
    private const OPT_LONG_FROM = 'from:';
    private const OPT_LONG_TO = 'to:';
    private const OPT_LONG_CLEAR = 'clear';
    private const OPT_LONG_TYPE = 'type:';

    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'search_buildIndex';

    private const LOCK_TTL = 3600; // 3600 seconds = 60 minutes = 1 hour.

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
     * * --from:  from date
     * * --to:    to date
     * * --clear: clear index completely
     * * --type:  if provided, only build indices of this type
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_FROM, self::OPT_LONG_TO, self::OPT_LONG_CLEAR, self::OPT_LONG_TYPE];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $logger = $this->getLogger();

        try {
            $fromDate = $this->buildDate($this->getOpt(self::OPT_LONG_FROM));
            $toDate   = $this->buildDate($this->getOpt(self::OPT_LONG_TO));
        } catch (InvalidArgumentException $e) {
            $logger->error($e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $logger->debug('Acquiring lock.');
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException) {
            return;
        }

        if ($this->perform()) {
            $logger->notice('Running in perform mode: search index will be rebuilt');
        } else {
            $logger->notice('Running in dry-run mode: provide the `-p` flag to rebuild index');
        }

        $logger->notice(
            sprintf(
                'Building search index from %s to %s',
                ($fromDate ? $fromDate->getDatabase() : 'earliest result'),
                ($toDate ? $toDate->getDatabase() : 'most recent result'),
            ),
        );

        // Run all queries against the write database, rather than a possible
        // read replica.
        // This ensures that `SET max_statement_time = 0` happens on the same
        // database as the `SELECT` statements.
        /** @var DatabaseFactory $dbFactory */
        $dbFactory = $this->getDi()->get('databaseFactory');
        $dbi       = $dbFactory->getInternalWriteDatabase();

        $types = $this->getOpt(self::OPT_LONG_TYPE);
        if (!isset($types)) {
            $logger->notice('Building search index for all result types');
            /** @var IBuilder $indexBuilder */
            $indexBuilder = $this->getDi()->get('search.indexBuilder');
            $indexBuilder = $indexBuilder->setLogger($logger);
        } else {
            // Build index builders from a list of candidate types
            $types                  = is_array($types) ? $types : [$types];
            $includedIndexBuilders  = [];
            $candidateIndexBuilders = [
                ResultType\Assessment\Hydrator::BASE_TYPE => new ResultType\Assessment\IndexBuilder($dbi, $logger),
                ResultType\Badge\Hydrator::BASE_TYPE      => new ResultType\Badge\IndexBuilder($dbi, $logger),
                ResultType\Blog\Hydrator::BASE_TYPE       => new ResultType\Blog\IndexBuilder($dbi, $logger),
                ResultType\Event\Hydrator::BASE_TYPE      => new ResultType\Event\IndexBuilder($dbi, $logger),
                ResultType\File\Hydrator::BASE_TYPE       => new ResultType\File\IndexBuilder($dbi, $logger),
                ResultType\Forum\Hydrator::BASE_TYPE      => new ResultType\Forum\IndexBuilder($dbi, $logger),
                ResultType\Link\Hydrator::BASE_TYPE       => new ResultType\Link\IndexBuilder($dbi, $logger),
                ResultType\News\Hydrator::BASE_TYPE       => new ResultType\News\IndexBuilder($dbi, $logger),
                ResultType\Textbox\Hydrator::BASE_TYPE    => new ResultType\Textbox\IndexBuilder($dbi, $logger),
                ResultType\Page\Hydrator::BASE_TYPE       => new ResultType\Page\IndexBuilder($dbi, $logger),
                ResultType\Tile\Hydrator::BASE_TYPE       => new ResultType\Tile\IndexBuilder($dbi, $logger),
                ResultType\User\Hydrator::BASE_TYPE       => new ResultType\User\IndexBuilder($dbi, $logger),
            ];
            foreach ($types as $type) {
                if (!isset($candidateIndexBuilders[$type])) {
                    $logger->error(
                        sprintf(
                            'Result type "%s" is not recognised (recognised types: %s): exiting',
                            $type,
                            implode(', ', array_keys($candidateIndexBuilders)),
                        )
                    );
                    exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
                }
                $includedIndexBuilders[] = $candidateIndexBuilders[$type];
            }
            $logger->notice(
                sprintf(
                    'Building search index for result types: %s',
                    implode(', ', $types),
                )
            );
            $indexBuilder = new CompositeBuilder(...$includedIndexBuilders);
        }
        $persistor = new Persistor($indexBuilder, $dbi);

        // Some queries run during the filling of the search_index table can take a long time to run.  It is crucial
        // that these queries be allowed to finish, because if they are interrupted, the search_index table will not be
        // filled, and the search feature will not work as expected.  So here we set max_statement_time to 0 (no limit).
        // https://mariadb.com/kb/en/aborting-statements/
        $dbi->execute('SET SESSION max_statement_time = 0;');
        $rows = $dbi->executeQuery("SHOW VARIABLES LIKE 'max_statement_time'")->fetchAll();
        $maxStatementTime = $rows[0]['Value'];
        $logger->info("Checking config: max_statement_time is $maxStatementTime.");

        // Set this on the server as well: I'm not sure this makes a difference
        $dbi->executeDirectly('SET MAX_STATEMENT_TIME=0');

        // Clear indices if required
        if ($this->getOpt(self::OPT_LONG_CLEAR)) {
            if (empty($types)) {
                $logger->notice('Clearing ALL existing indices before rebuilding!');
                if ($this->perform()) {
                    $persistor->clearAllIndices();
                }
            } else {
                $logger->notice('Clearing indices for provided types before rebuilding!');
                foreach ($types as $type) {
                    $logger->notice(sprintf('Clearing indices for type: %s', $type));
                    if ($this->perform()) {
                        $persistor->clearIndicesOfType($type);
                    }
                }
            }
        }

        // Actually create data
        $time = microtime(true);
        try {
            // Refresh lock before building list of updates
            $logger->debug('Refreshing lock.');
            $this->refreshLock($lock);

            foreach ($persistor->buildUpdates($fromDate, $toDate, null) as $updatedIndex) {
                if (!$this->perform()) {
                    continue;
                }

                // Refresh lock between each update
                $this->refreshLock($lock);

                try {
                    $persistor->updateIndex($updatedIndex);
                } catch (Exception $e) {
                    $logger->warning(
                        sprintf(
                            'Unexpected database error when index for "%s:%s" (%s): ignoring',
                            $updatedIndex->getType(),
                            $updatedIndex->getIdentifier(),
                            $e->getMessage(),
                        )
                    );
                    continue;
                }
            }

            foreach ($persistor->buildDeletes($fromDate, $toDate, null) as $deletedIndex) {
                if (!$this->perform()) {
                    continue;
                }
                $persistor->deleteIndex($deletedIndex);
            }
        } catch (LockRefreshException) {
            // Do not carry on: instead, exit politely
            $logger->warning('Exiting early as lock could not be re-acquired');
            return;
        }

        $logger->notice(sprintf("Search indices rebuilt in %0.2fs", microtime(true) - $time));

        // Run OPTIMIZE TABLE after fully rebuilding the search_index table.
        // Note running OPTIMIZE TABLE on an InnoDB table is equivalent to:
        //  1. Recreating the table
        //  2. Running ANALYZE TABLE
        // The purpose of running OPTIMIZE TABLE is to prevent errors of two types from occurring:
        //  * This error: https://stackoverflow.com/questions/64397678/mysql-match-relevance-score-causes-double-value-is-out-of-range-error
        //    (This will be fixed by the 'ANALYZE TABLE' command)
        //  * Another type of error where if the FULLTEXT index is missing or broken, MySQL will just silently disregard
        //    it, and return zero for any related MATCH...AGAINST commands instead of the expected non-zero return value.
        //    (This type of error will not be fixed by ANALYZE TABLE alone - it requires that the table be rebuilt as well,
        //     which is why we run OPTIMIZE instead of ANALYZE).
        // Both types of errors described above have been seen in testing.
        if ($fromDate === null && $toDate === null) {
            $time = microtime(true);
            $logger->notice("Running OPTIMIZE TABLE search_index...");
            $dbi->execute('OPTIMIZE TABLE search_index');
            $logger->notice(sprintf("Finished running OPTIMIZE TABLE search_index.  Time taken: %0.2fs", microtime(true) - $time));
        }

        $logger->debug('Releasing lock.');
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
        $indexTypes = implode(
            "\n        * ",
            [
                ResultType\Assessment\Hydrator::BASE_TYPE,
                ResultType\Badge\Hydrator::BASE_TYPE,
                ResultType\Blog\Hydrator::BASE_TYPE,
                ResultType\Event\Hydrator::BASE_TYPE,
                ResultType\File\Hydrator::BASE_TYPE,
                ResultType\Forum\Hydrator::BASE_TYPE,
                ResultType\Link\Hydrator::BASE_TYPE,
                ResultType\News\Hydrator::BASE_TYPE,
                ResultType\Textbox\Hydrator::BASE_TYPE,
                ResultType\Page\Hydrator::BASE_TYPE,
                ResultType\Tile\Hydrator::BASE_TYPE,
                ResultType\User\Hydrator::BASE_TYPE,
            ]
        );
        echo <<<HELP
NAME:
    Search index builder

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Builds this Schoolbox instance's search indices.

    This script may build indices for only a given date span: it will leave indices outside this date span untouched.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually build search indices: otherwise, only output what would be
        built.

    $yellow--from=FROM_DATE$white
        If provided, only build indices for results from {$yellow}FROM_DATE{$white} onward.

        {$yellow}FROM_DATE{$white} will be parsed according to the following rules:
        * as a database date (YYYY-MM-DD HH:MM:SS)
        * as a Unix timestamp
        * via `strtotime`

        This script will fail if {$yellow}FROM_DATE{$white} is not parseable.

    $yellow--to=TO_DATE$white
        If provided, only build indices for results to {$yellow}TO_DATE{$white} and before.

        {$yellow}TO_DATE{$white} will be parsed using the same rules as {$yellow}FROM_DATE{$white}, and will cause this script to fail for the same reasons.

    $yellow--clear$white
        If provided, clear all existing indices before rebuilding the index.
        (If $yellow--type$white is provided, only clear this type of index.)

        Has no effect without the $yellow-p$white or $yellow--perform$white flags.

    $yellow--type=TYPE$white
        If provided, only rebuild indices of this type. (May be provided more
        than once to rebuild multiple index types.)

        The following index types are able to be built by this script:
        * {$indexTypes}


HELP;
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
}
