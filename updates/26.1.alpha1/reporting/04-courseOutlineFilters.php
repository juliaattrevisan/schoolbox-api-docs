<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
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

Reporting Activity - Course Outline Filters Migration

This updates all existing course outline component filters that haven't been changed
from the default values to include Reporting Activity (hence remaining consistent with the new default).

This always runs in perform mode as part of the upgrade.

Usage: 04-courseOutlineFilters.php [-vvv]


HELP;
            return;
        }

        $db = $this->getDatabaseInternal();

        $ltiEnabled = $this->getConfig()->get('lti_assessments_enable');

        /**
         * Update all course outline "assessmentTypes" config instances that are still at their
         * default values (all non-reporting types enabled, nothing filtered out) to include
         * Reporting Activity.
         *
         * Two query variants handle whether LTI is enabled: when LTI is disabled, default configs
         * would not have "lti":"1", so we don't require it as part of the default check.
         *
         * Note: component_config_instance.value is a shared varchar(2048) column that also stores
         * non-JSON values for other config keys. The multi-table UPDATE optimiser evaluates the
         * predicates against rows before the key/class joins fully filter them, so JSON_EXTRACT
         * would raise "Syntax error in JSON text" (error 4038) on those non-JSON values. JSON_VALUE
         * is lenient (returns NULL on invalid JSON) and also unquotes the scalar, so its result
         * compares correctly against '1'. Do not switch these back to JSON_EXTRACT.
         */
        if ($ltiEnabled) {
            $sql = "
                UPDATE component_config_instance cci
                INNER JOIN component_config_definition ccd
                    ON cci.component_config_definition_id = ccd.id
                    AND ccd.`key` = 'assessmentTypes'
                INNER JOIN component_instance ci
                    ON cci.component_instance_id = ci.id
                INNER JOIN component_definition cd
                    ON ci.component_definition_id = cd.id
                    AND cd.class = 'Schoolbox\\\\Learning\\\\Assessment\\\\Component\\\\Homepage\\\\Outline\\\\Controller'
                SET cci.value = JSON_SET(cci.value, '$.reporting', '1')
                WHERE JSON_VALUE(cci.value, '$.project')     = '1'
                  AND JSON_VALUE(cci.value, '$.dueWork')     = '1'
                  AND JSON_VALUE(cci.value, '$.task')        = '1'
                  AND JSON_VALUE(cci.value, '$.quiz')        = '1'
                  AND JSON_VALUE(cci.value, '$.lessonPlan')  = '1'
                  AND JSON_VALUE(cci.value, '$.dialogAgent') = '1'
                  AND JSON_VALUE(cci.value, '$.lti')         = '1'
                  AND (JSON_VALUE(cci.value, '$.reporting') IS NULL
                    OR JSON_VALUE(cci.value, '$.reporting') != '1')
            ";
        } else {
            $sql = "
                UPDATE component_config_instance cci
                INNER JOIN component_config_definition ccd
                    ON cci.component_config_definition_id = ccd.id
                    AND ccd.`key` = 'assessmentTypes'
                INNER JOIN component_instance ci
                    ON cci.component_instance_id = ci.id
                INNER JOIN component_definition cd
                    ON ci.component_definition_id = cd.id
                    AND cd.class = 'Schoolbox\\\\Learning\\\\Assessment\\\\Component\\\\Homepage\\\\Outline\\\\Controller'
                SET cci.value = JSON_SET(cci.value, '$.reporting', '1')
                WHERE JSON_VALUE(cci.value, '$.project')     = '1'
                  AND JSON_VALUE(cci.value, '$.dueWork')     = '1'
                  AND JSON_VALUE(cci.value, '$.task')        = '1'
                  AND JSON_VALUE(cci.value, '$.quiz')        = '1'
                  AND JSON_VALUE(cci.value, '$.lessonPlan')  = '1'
                  AND JSON_VALUE(cci.value, '$.dialogAgent') = '1'
                  AND (JSON_VALUE(cci.value, '$.reporting') IS NULL
                    OR JSON_VALUE(cci.value, '$.reporting') != '1')
            ";
        }

        $result = $db->executeQuery($sql);

        $this->getLogger()->info(sprintf(
            '%s course outline component config instances matched defaults and have been updated to include Reporting Activity',
            $result->rowCount(),
        ));
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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
