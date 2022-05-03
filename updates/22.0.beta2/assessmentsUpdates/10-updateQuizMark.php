<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
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
     * @var Schoolbox\Learning\Quiz\Attempt\Mapper
     */
    private Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper;


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

Once-off correctional tool

This updates all quiz responses to contain the mark in the assess_response table,
not require the system to build the mark and max mark each time the quiz is viewed.

This always runs in perform mode as part of the v22 update

Usage: php 00-updateQuizMark.php --perform


HELP;
            exit;
        }

        $this->attemptMapper = Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance();


        $this->getLogger()->debug('updating all attempts.');
        /**
         * get list of quiz responses to update
         */
        /** @var Schoolbox\Learning\Quiz\Attempt\Model[] $attempts */
        $attempts = $this->attemptMapper->findAll();

        /**
         * do the update
         */
        foreach ($attempts as $attempt) {
            $this->getLogger()->debug(sprintf(
                'Updating marks for attempt: %d',
                $attempt->getId()
            ));
            $this->attemptMapper->updateMarks($attempt);
        }


        $this->getLogger()->debug('Done.');
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
