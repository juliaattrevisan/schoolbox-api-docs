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
class GroupListTool extends CliTool\App
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

Group List tool to inspect what Turnitin knows about its Groups;
(a Turnitin Group is synonymous with our Submission Box)

We had information that some Groups were missing two key fields due to me not
supplying them in the Group tool.
  * due_at
  * report_generation

This inspector finds all the turnitin group ids that we know about in an instance,
asks Turnitin to fetch each Group, then show it on the cli. That way we can see
what Turnitin knows. They don't provide a gui to do it.

This tool does not mutate data, it only reads and interrogates a third party.

Usage: grouplist.php


HELP;
            exit;
        }

        $this->getLogger()->info('Interrogating Turnitin about its Groups.');

        $groups = \Schoolbox\Learning\Turnitin\Group\Mapper::getInstance()->findAll();

        /* No Groups? No trabajo
         */
        if (!$groups->count()) {
            $this->getLogger()->info(sprintf(
                'No turnitin_group records - exiting'
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
        foreach ($groups as $group) {
            if (!$group->getGroupId()) {
                $this->getLogger()->info(sprintf(
                    'Submission box %s has not been processed by Turnitin yet; it does not have a Turnitin Group identifier',
                    $group->getSubmissionBoxId()
                ));

                continue;
            }

            /* Pump to cli for visual inspection
             */
            $turnitinGroup = $api->retrieve('GET', "/api/v1/groups/{$group->getGroupId()}", []);

            $this->getLogger()->info(sprintf(
                'Turnitin Group "%s" (%s) - due_date: %s, report_generation: %s',
                $turnitinGroup['name'],
                $turnitinGroup['id'],
                $turnitinGroup['due_date'],
                $turnitinGroup['report_generation']
            ));
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
