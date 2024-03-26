<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Url\Factory;

/**
 * Learnosity Job Status
 *
 * Find out the current status of the job_reference provided
 */
class JobStatusTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * --jobreference
     *
     * Find out the status of a learnosity job reference
     */
    private const OPT_LONG_JOB_REFERENCE = 'jobreference:';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_JOB_REFERENCE
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Ask learnosity about the current status of a given job reference. 

This tool doesn't mutate any data; it just tells you things.

Usage: jobstatus.php --jobreference=... [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Job Status.');
        $this->getLogger()->info('');

        /* Check mandatory args first
         */
        $jobReference = trim((string)$this->getOpt(self::OPT_LONG_JOB_REFERENCE));

        if ('' === $jobReference) {
            $this->getLogger()->info('Please specify a learnosity job reference --jobreference');

            exit;
        }

        /* Build dependencies
         */
        $api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        $response = $api->dataGetRequest('/jobs', [
            'references' => [
                $jobReference
            ]
        ]);

        print_r($response);
    }
}
