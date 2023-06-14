<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

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
     * Delete config instances associated with this component definition
     */
    private const DELETE_CONFIG_INSTANCES = "
        DELETE cci
        FROM component_config_instance cci
        LEFT JOIN component_config_definition ccd ON ccd.id = cci.component_config_definition_id
        LEFT JOIN component_instance ci ON cci.component_instance_id = ci.id
        LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        WHERE cd.class = :controllerClass
    ";


    /**
     * Delete config definitions associated with this component definition
     */
    private const DELETE_CONFIG_DEFINITIONS = "
        DELETE ccd
        FROM component_config_definition ccd
        LEFT JOIN component_definition cd ON ccd.component_definition_id = cd.id
        WHERE cd.class = :controllerClass
    ";


    /**
     * Delete component instances associated with this component definition
     */
    private const DELETE_COMPONENT_INSTANCES = "
        DELETE ci
        FROM component_instance ci
        LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        WHERE cd.class = :controllerClass
    ";


    /**
     * Delete component definition associated with this component
     */
    private const DELETE_COMPONENT_DEFINITIONS = "
        DELETE
        FROM component_definition
        WHERE component_definition.class = :controllerClass
    ";


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

Deletes the SMGS St Michaels and LC Loyola Backpack Survey components from everyone
except those respective schools.

There is no perform mode; it always persists changes.

Usage: 02-component-removal.php


HELP;
            return;
        }

        $siteLocation = strtolower($this->getConfig()->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));

        if ('smgs' !== $siteLocation) {
            /* Delete the Awards component for everyone except SMGS St Michaels
             */
            $this->purgeComponent('Component_Dashboard_StMichaelsAwardsController');
        }

        if ('lc' !== $siteLocation) {
            /* Delete the Survey Backpack component for everyone except LC Loyola College
             */
            $this->purgeComponent('Custom\\Lc\\Survey\\Component\\Eportfolio\\Controller');
        }
    }


    /**
     * Delete all four organs of a component to remove it entirely
     *
     * @param string $controllerClass
     * @return void
     */
    private function purgeComponent(string $controllerClass): void
    {
        $this->getLogger()->debug(sprintf(
            'Deleting component config instances belonging to %s',
            $controllerClass
        ));

        $this->getDatabaseInternal()->executeQuery(self::DELETE_CONFIG_INSTANCES, [
            'controllerClass' => $controllerClass
        ]);

        $this->getLogger()->debug(sprintf(
            'Deleting component config definitions belonging to %s',
            $controllerClass
        ));

        $this->getDatabaseInternal()->executeQuery(self::DELETE_CONFIG_DEFINITIONS, [
            'controllerClass' => $controllerClass
        ]);

        $this->getLogger()->debug(sprintf(
            'Deleting component instances belonging to %s',
            $controllerClass
        ));

        $this->getDatabaseInternal()->executeQuery(self::DELETE_COMPONENT_INSTANCES, [
            'controllerClass' => $controllerClass
        ]);

        $this->getLogger()->debug(sprintf(
            'Deleting component definition belonging to %s',
            $controllerClass
        ));

        $this->getDatabaseInternal()->executeQuery(self::DELETE_COMPONENT_DEFINITIONS, [
            'controllerClass' => $controllerClass
        ]);
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
        return null;
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
