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

        $command = sprintf("/home/vagrant/intelliscl/bin/intelliscl sync --deployment %s --secret %s --connString1 '%s' --tempFolder '%s'", escapeshellarg($deploymentId), escapeshellarg($secret), SQL_dsn_read, sys_get_temp_dir());

        $this->getLogger()->debug($command);

        exec($command, $output);

        foreach ($output as $line) {
            $this->getLogger()->debug($line);
        }
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
