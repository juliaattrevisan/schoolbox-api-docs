<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Exception\Exception;

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
class SubmissionInfoTool extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * The starting chars of tii submission ids
     * Anything starting with these are matched and analysed
     */
    private const OPT_LONG_TII_SUBMISSION_IDS = 'id:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_TII_SUBMISSION_IDS
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Submission Info tool to inspect what Turnitin knows about its Submissions;
(a Turnitin Submission is synonymous with our Student Submission File)


This inspector finds all the turnitin submission ids that we know about in an instance,
then show it on the cli. That way we can see what Turnitin knows. They don't provide a 
gui to do it.

This tool does not mutate data, it only reads and interrogates a third party.

Usage: submissionInfo.php

You can specify partial turnitin submission ids to only return results pertinent to
reports that match those ids. In the following example usage, any turnitin submission
id starting with abcd and qwe123 will be analysed; others are ignored.

Usage: submissionInfo.php --id=abcd,qwe123


HELP;
            exit;
        }

        $this->getLogger()->info('Interrogating Turnitin about its Submissions.');

        $mapper = \Schoolbox\Learning\Turnitin\File\Mapper::getInstance();

        if ('' !== $this->getOpt(self::OPT_LONG_TII_SUBMISSION_IDS)) {
            /* Only find info about particular submissions
             */
            $ids = explode(',', $this->getOpt(self::OPT_LONG_TII_SUBMISSION_IDS));

            $stack = [];

            foreach ($ids as $id) {
                $stack[] = "{$id}%";
            }

            $pb = $mapper->getPredicateBuilder();
            $pb->likeAny('turnitin_submission_id', $stack);

            $reports = $mapper->findByPredicates($pb);
        } else {
            /* Find all the submissions and get info about all of those
             */
            $reports = $mapper->findAll();
        }

        /* No Groups? No trabajo
         */
        if (!$reports->count()) {
            $this->getLogger()->info(sprintf(
                'No turnitin_report records - exiting'
            ));

            exit;
        }

        /* Check that your api setup is functioning
         * We need this api working
         */
        $api = new Turnitin\Api(
            $this->getConfig()->get('site_version'),
            $this->getConfig()->get('turnitin_api_host'),
            $this->getConfig()->get('turnitin_api_key')
        );

        /* If the basic test() failed, then your turnitin config is broken
         * and your api host/key combo is somehow wrong
         */
        try {
            $api->test();
        } catch (Exception $e) {
            $this->getLogger()->warning($e->getMessage());
            exit(1);
        }

        /* Let's look inside Turnitin's mind
         */
        foreach ($reports as $report) {
            if (!$report->getTurnitinSubmissionId()) {
                $this->getLogger()->info(sprintf(
                    'Student Submission File %s has not been processed by Turnitin yet; it does not have a Turnitin Submission identifier',
                    $report->getStudentSubmissionFileId()
                ));

                continue;
            }

            /* Pump to cli for visual inspection
             */
            $turnitinSubmissionInfo = $api->retrieve('GET', "/api/v1/submissions/{$report->getTurnitinSubmissionId()}", []);
            $turnitinReportInfo = $api->retrieve('GET', "/api/v1/submissions/{$report->getTurnitinSubmissionId()}/similarity", []);

            $this->getLogger()->debug(sprintf(
                'Turnitin report id %s',
                $report->getId()
            ));

            /* There isn't a convenient way to dump all this
             */
            print_r($turnitinSubmissionInfo);
            print_r($turnitinReportInfo);
            print_r("\n");
        }

        exit();
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
}
