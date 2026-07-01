#!/usr/bin/env php
<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.education>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This tool will clean up the video queue log and remove any successful run logs from over 3 months ago.
 * This prevents the video_queue table from becoming very large
 */
$app = new class extends CliTool\BaseApp
{
    protected function getName(): string
    {
        return 'videolog';
    }

    protected function getDescription(): string
    {
        return 'Periodically cleans the message field from the video_queue table. Anything older than 3 months from the time of running is deleted permanently.';
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return true;
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if (!$this->perform()) {
            $this->getLogger()->info('Searching for records to clean.');

            /* Planning mode
             */
            $statement = $this->getDatabaseInternal()->executeQuery("
                SELECT *
                FROM video_queue
                WHERE status='completed' AND modifiedOn < DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND message != ''
            ");

            while ($row = $statement->fetch()) {
                $this->getLogger()->info(sprintf(
                    '%s: %s %s',
                    $row['video_queue_id'],
                    $row['hash'],
                    $row['status']
                ));
            }

            $this->getLogger()->info('Planning finished.');
        } else {
            /* Perform mode
             */
            $this->getDatabaseInternal()->executeQuery("
                SET STATEMENT max_statement_time=0 FOR
                UPDATE video_queue SET message = '' WHERE status='completed' AND modifiedOn < DATE_SUB(CURDATE(), INTERVAL 3 MONTH) and message != ''
            ");
        }
    }


    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};


$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
