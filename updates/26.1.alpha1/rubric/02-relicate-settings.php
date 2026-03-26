<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
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

Replicate settings for rubric grids

This clones out settings for each submission_box's rubric settings into rubric_instance, mapping submission_box.id to
its corresponding assessment_id. Capability settings are simply copied into rubric_instance accordingly. use_rubric is
used to decide which submission boxes get their settings cloned: anything with a falsey use_rubric value will be ignored
under the premise that you didn't want rubrics there (even if it had rubric settings) because you turned it off

This tool is intended to be a one-use-only as part of an upgrade path and always runs in perform mode.

Usage: 02-replicate-settings.php [-vvv]


HELP;
            return;
        }


        $this->getLogger()->info('Cloning submission_box rubric settings into rubric_instance');


        /* Find all the submission_boxes that:
         *    - have a truthy use_rubric value
         *    - are still alive (deleted_at is null)
         *    - are connected to a still-alive assessment (in the odd circumstance that we had bad data)
         */

        /* For each record in that result set, insert a new row into rubric_instance so that we:
         *    - save the rubric capability mark type setting
         *    - save the rubric capability grade group setting
         *    - save the mark range boolean setting
         *    - save the corresponding assessment_id so we know which assessment has this grid
         */

        /* Compare how many we expected to find vs how many we actually manufactured as an assertion we did it all
         */

        /* Add a timer
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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
