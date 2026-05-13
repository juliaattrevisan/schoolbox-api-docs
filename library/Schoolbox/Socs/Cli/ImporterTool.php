<?php

/**
 * SOCS Fixtures CLI Importer
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Cli;

use Ramsey\Uuid\Uuid;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Socs\Factory as SocsFactory;
use Schoolbox\Socs\SOCSFatalException;
use Schoolbox\Socs\Log\Mapper as SocsLogMapper;

/**
 * CLI wrapper for the SOCS importer.
 *
 * This mirrors the Clipboard importer: it acquires the lock, reads
 * configuration, constructs a SOCS factory and executes a pipeline of
 * strategies (prepare, import, cleanup, statistics).
 */
class ImporterTool extends CliTool\App
{
    public const string LOCK_NAME = 'socs_importer';
    private const string OPT_LONG_HELP = 'help';
    private const string OPT_LONG_INSTANCE = 'instance:';
    private const string OPT_LONG_FIXTURES = 'fixtures';
    private const string OPT_LONG_RESULTS = 'results';
    private const string OPT_LONG_TEAMS = 'teams';
    private const int LOCK_TTL = 120;
    private const int DEFAULT_TIMEOUT = 3600;

    /**
     * Declare long CLI options accepted by the SOCS importer.
     *
     * @return string[]
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_FIXTURES,
            self::OPT_LONG_RESULTS,
            self::OPT_LONG_TEAMS,
        ];
    }

    /**
     * Entry point for the SOCS importer CLI.
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            return;
        }

        $logger = $this->getLogger();

        if (!$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENABLED)) {
            $logger->info('SOCS integration is not enabled; exiting');
            return;
        }

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            $logger->warning('Cannot acquire SOCS importer lock. Likely previous execution still in progress. Exiting');
            return;
        }

        $this->setMaxExecutionTime(self::DEFAULT_TIMEOUT);

        $runFixtures = (bool)$this->getOpt(self::OPT_LONG_FIXTURES);
        $runResults = (bool)$this->getOpt(self::OPT_LONG_RESULTS);
        $runTeams = (bool)$this->getOpt(self::OPT_LONG_TEAMS);

        // if nothing is set, run them all
        if (!$runFixtures && !$runResults && !$runTeams) {
            $runFixtures = $runResults = $runTeams = true;
        }

        $host = (string)$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST);
        $token = (string)$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN);
        $schoolId = (string)$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENDPOINT_ID);

        $runId = Uuid::uuid4()->toString();
        $importLogMapper = SocsLogMapper::getInstance();
        $importLog = null;

        $scriptParts = [];
        if ($runFixtures) {
            $scriptParts[] = 'fixtures';
        }
        if ($runResults) {
            $scriptParts[] = 'results';
        }
        if ($runTeams) {
            $scriptParts[] = 'teams';
        }
        $scriptName = sprintf('socs:%s', implode('+', $scriptParts));

        try {
            $composites = (new SocsFactory(
                $logger,
                $this->perform(),
                $lock,
                $host,
                $schoolId,
                $token
            ))->produce(
                $runFixtures,
                $runResults,
                $runTeams
            );
        } catch (\DomainException $e) {
            if ($this->perform()) {
                $importLog = $importLogMapper->start($runId, $scriptName);
                $importLogMapper->error($importLog, $e->getMessage());
                $importLogMapper->prune();
            }

            $logger->warning($e->getMessage());
            $lock->release();

            return;
        }

        if ($this->perform()) {
            $importLog = $importLogMapper->start($runId, $scriptName);
            $importLogMapper->prune();
        }

        try {
            foreach ($composites as $composite) {
                $logger->debug(sprintf(
                    'Refreshing SOCS importer lock for another %s seconds',
                    self::LOCK_TTL
                ));

                $lock->refresh();

                $composite->import();
            }

            if (!$this->perform()) {
                $logger->info('SOCS importer planning finished.');
            } else {
                $importLogMapper->finish($importLog);
            }
        } catch (\Exception | SOCSFatalException $e) {
            if ($this->perform()) {
                $importLogMapper->error($importLog, $e->getMessage());
            }

            $logger->warning($e->getMessage());
            $logger->debug($e->getTraceAsString());

            if ($e->getPrevious()) {
                $logger->debug(sprintf('Previous: %s', $e->getPrevious()->getMessage()));
                $logger->debug($e->getPrevious()->getTraceAsString());
            }

            $lock->release();
        }
    }

    /**
     * Print usage information for the SOCS importer CLI.
     */
    private function printHelp(): void
    {
        echo <<<HELP

Import SOCS sport fixtures into the Schoolbox Fixtures module using the
SOCS XML fixtures feed, synchronise SOCS results via the results feed,
and synchronise SOCS teams via the mso-sport feed.

Usage: php importer.php [--perform] [--fixtures] [--results] [--teams]

When --perform is omitted, the importer will run in planning mode and
log what would happen without persisting any changes.

When none of --fixtures, --results or --teams are provided, all
synchronisations will run. When one or more are provided, only the
selected synchronisations will run.

HELP;
    }
}
