<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;

/**
 * Command-line tool for triggering an intellischool sync.
 */
class Sync extends App
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * Short options:
     * * -h: help
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * Long options:
     * * --help:  help
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        /**
         * get sync parameters
         */
        $deploymentId = $this->getConfig()->get('intellischool_deployment_id');
        $secret = $this->getConfig()->get('intellischool_deployment_secret');

        // Check if intellischool is setup or exit early
        if ('' === $deploymentId || '' === $secret) {
            $this->getLogger()->debug("intellischool not setup, exiting...");
            exit();
        }
        $sql = sprintf('jdbc:mysql://%s:3306/%s?user=%s&password="%s"', SQL_host_read, SQL_db_read, urlencode(SQL_user_read), urlencode(SQL_passwd_read));
        $command = sprintf("intelliscl sync --deployment %s --secret %s --connString1 %s --tempFolder '%s'", escapeshellarg($deploymentId), escapeshellarg($secret), escapeshellarg($sql), sys_get_temp_dir());

        $this->getLogger()->debug($command);

        passthru($command, $output);

        exit($output);
    }

    /**
     * Print help text
     *
     * @return void
     */
    private function printHelp(): void
    {
        $file       = $this->getToolFilename();
        $green      = self::COLOUR_GREEN;
        echo <<<HELP
NAME:
    Sync Intellischool

SYNOPSIS:
    $green{$file}

DESCRIPTION
    Will only sync if intellischool is setup on the instance,
    else it will just exit quietly

HELP;
    }
}
