#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Geosnapshot\Api;
use Schoolbox\Geosnapshot\Payload\RedactedPhotoList;

require_once __DIR__ . '/../../conf/core/require.php';

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

Geosnapshot Redactions send a list of students to Geosnapshot, describing which humans
had their pictures redacted via the sis. Also transmits some additional metadata.

If you enter planning mode, it'll show you a list of redacted dudes only

Usage: php redaction.php [--perform]


HELP;
            exit;
        }

        $this->getLogger()->info('Geosnapshot Redactions.');

        $fqdn = (new Schoolbox\Url\Factory($this->getConfig()))->buildFullyQualified()->toString();
        $redactionList = (new External_User())->getPhotoRedactionList();

        /* Generate a list of guys who are about to be redacted
         * Normally the api would quietly compile this and transmit it
         */
        if (!$this->perform()) {
            $payload = (new RedactedPhotoList($fqdn))->build($redactionList);
            $numVisible = 0;

            foreach ($payload as $redaction) {
                if ($redaction['redacted']) {
                    $this->getLogger()->debug(sprintf(
                        'external_id %s (year: %s, house: %s) redacted at %s',
                        $redaction['ext_id'],
                        $redaction['attributes']['year'] ?: 'null',
                        $redaction['attributes']['house'] ?: 'null',
                        $redaction['redact_photo_modified_at']
                    ));
                } else {
                    $numVisible++;
                }
            }

            $this->getLogger()->debug(sprintf(
                '%s human(s) have visible photos (they are not redacted)',
                $numVisible
            ));

            $this->getLogger()->info('Planning finished.');
        } else {
            /* Perform transmission
             */
            (new Api($this->getConfig()->get('geosnapshot_api_key')))
                ->retrieveRedactionPhotos($fqdn, $redactionList);
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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
