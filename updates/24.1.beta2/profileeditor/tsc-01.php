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
$app = new class extends CliTool\App {
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'profileEditor_customwork_tsc';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --flag
     *
     * An example arg that's a boolean on/off
     */
    private const OPT_LONG_FLAG = 'flag';


    /**
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FLAG,
            self::OPT_LONG_INPUT
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Profile Editor: TSC Custom Work migration.


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        $this->insertCustomFields();
        $this->insertCustomButtons();

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Their connector surfaces LECaseManagerName, LECaseManagerUserId
     * optionally displaying it in the profile screens if they're meaningful
     *
     * @return void
     */
    private function insertCustomFields(): void
    {
        $query = "
            INSERT INTO `profile_field`
            (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
            VALUES
            ('student','LECaseManagerName','Learning Enrichment Case Manager',(
            SELECT MAX(sequence)+1
            FROM profile_field pf
            WHERE roletype = 'student'
            ),1,NULL,NULL,NULL,0,1);
        ";

        $this->getDatabaseInternal()->executeQuery($query);

        /* Despite this field existing, it'll never render to users: a customised student.twig
         * intercepts the default rendering
         */
        $query = "
            INSERT INTO `profile_field`
            (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
            VALUES
            ('student','LECaseManagerID','Learning Enrichment Case Manager ID',(
            SELECT MAX(sequence)+1
                FROM profile_field pf
                WHERE roletype = 'student'
            ),1,NULL,NULL,NULL,0,1)
        ";

        $this->getDatabaseInternal()->executeQuery($query);
    }


    /**
     * They also had a custom button in the Student roletype which would have been
     * visible to Parent or Staff
     *
     * @return void
     */
    private function insertCustomButtons(): void
    {

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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
