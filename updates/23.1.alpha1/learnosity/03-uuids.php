<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Ramsey UUID Assignation for Learnosity

Assigns a uuid4 Ramsey uuid into courses and units so we can identify
from whence a Learnosity item came. Intended as a once-off population
of uuids as part of a major version's update.


HELP;
            return;
        }

        /* Find all the course_specification rows that don't have a uuid already
         *
         * (This should be all of them at this point, but for the sake of safety,
         * you won't be able to accidentally overwrite anything that exists if you
         * accidentally ran this tool again)
         */
        $this->populateUuids('course_specification');

        /* Find all the course_implementation rows that don't have a uuid already
         */
        $this->populateUuids('course_implementation');

        /* Find all the course_unit_specification rows that don't have a uuid already
         */
        $this->populateUuids('course_unit_specification');

        /* Find all the course_unit_implementation rows that don't have a uuid already
         */
        $this->populateUuids('course_unit_implementation');

    }


    /**
     * Populate uuid column in $table
     *
     * @param string $table
     * @return void
     */
    private function populateUuids(string $table): void
    {
        $db = $this->getDatabaseInternal();

        $this->getLogger()->info(sprintf(
            'Learnosity: Populating v4 uuids in %s',
            $table
        ));

        $updateStatement = $db->prepareQuery("
            UPDATE {$table}
            SET uuid = :uuid
            WHERE id = :id
            LIMIT 1
        ");

        $statement = $db->prepareQuery("
            SELECT * 
            FROM {$table}
            WHERE uuid IS NULL
        ");

        $statement->execute();

        /* Make a new Gordon Ramsey UUID and save it in there cheaply
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $updateStatement->execute([
                'id' => $row['id'],
                'uuid' => \Ramsey\Uuid\Uuid::uuid4(),
            ]);
        }
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
