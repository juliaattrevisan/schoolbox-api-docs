#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;


require_once __DIR__ . '/../conf/core/require.php';

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
            self::OPT_LONG_HELP
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Updates the mime types for RTF and AVI files. These use application/rtf and video/x-msvideo mimetypes.
If they already exist in an Allowed Files table, nothing changes.

HELP;
            exit;
        }

        $this->getLogger()->info('Script commencing.');

        /* application/rtf
         *
         * Find out if this row already exists in Allowed Files. If it already exists,
         * we don't need to do anything.
         */

        /* Otherwise, we need to find the old version: text/richtext. If that exists,
         * update it with application/rtf instead and leave all the other settings intact.
         */

        /* If text/richtext doesn't exist for some reason, create a new row with application/rtf
         * and some default information about richtext file types.
         */

        /* video/x-msvideo
         *
         * Find out if this row already exists in Allowed Files. If it already exists,
         * we don't need to do anything.
         */

        /* Otherwise, we need to find the old version: video/avi. If that exists,
         * update it with video/x-msvideo instead and leave all the other settings intact.
         */

        /* If video/avi doesn't exist for some reason, create a new row with video/x-msvideo
         * and some default information about avi file types.
         */

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
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
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [ CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL, ];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
