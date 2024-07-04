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

Converts HTML comments to plaintext as part of an upgrade.


HELP;
        }

        $this->getLogger()->info('Commencing learnosity html-to-text comment conversion.');

        /* Commence one-way migration
         */
        $db = $this->getDatabaseInternal();

        /* Update query: prepare only once
         */
        $updateQuery = "
            UPDATE assess_attempt
            SET comment = :comment
            WHERE attempt_id = :attemptId
            LIMIT 1
        ";

        $updateStatement = $db->prepare($updateQuery);

        /* We only need attempts which had comments
         */
        $attemptQuery = "
            SELECT *
            FROM assess_attempt
            WHERE LENGTH(`comment`) > 0
        ";

        $statement = $db->prepare($attemptQuery);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $this->getLogger()->info(sprintf(
                'Attempt %s: converting overall comment to plaintext',
                $row['attempt_id']
            ));

            $updateStatement->execute([
                'attemptId' => $row['attempt_id'],
                'comment' => \Scrub::html2text($row['comment'])
            ]);
        }

        /* TODO html comments per question
         *  Comments in quiz_feedback are still html, because they never
         *  surface beyond their student review/teacher feedback screens.
         *  You could give them the same treatment here, if needed, but
         *  to minimise blast radius I've left them out for now.
         */
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
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
