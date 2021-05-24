#!/usr/bin/php
<?php

/**
 * This script can be used to force videos to be rencoded using modern ffmpeg encoding settings that work better
 * with IOS devices on 3g/4g networks.
 *
 * This script takes two parameters, the date you want to go back to and the amount of videos you want to trigger to
 * rencode.  Example;
 *     renecode-for-ios.php --date 2020-01-01 --limit 100
 *
 * Will cause at most 100 videos that were created after 2020-01-01 to be set to the pending state in the video queue
 *
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'date:',
            'limit:',
        ];
    }

    /**
     * Displays a help message
     */
    private function displayHelp(): void
    {
        echo <<<HELP
This script can be used to force videos to be rencoded using modern ffmpeg encoding settings
that work better with IOS devices on 3g/4g networks.

Usage: renecode-for-ios.php [--date 2020-01-01] [--limit 100]
    --date:             Only renecode videos uploaded after this date
                         Defaults to 2020-01-01.
    --quantity:         The total number of videos that will be set to rencode
                         Defaults to 10.

HELP;
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt('help')) {
            $this->displayHelp();
            exit(0);
        }

        $db = $this->getDatabaseInternal();
        $date = $this->getOpt('date:') ?? '2020-01-01';
        $limit = $this->getOptAsInt('limit:', 10);


        $query = "  UPDATE video_queue SET status='pending', modifiedOn=NOW()
                    WHERE createdOn > :date AND status = 'completed' AND message not like '%-movflags faststart%' AND message not like '%-map 0:a%'
                    LIMIT $limit
                 ";

        $statement = $db->prepareQuery($query);
        $statement->execute(['date' => $date]);

        $this->getLogger()->info(sprintf('Updated %d videos to pending', $statement->rowCount()));
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};


$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
