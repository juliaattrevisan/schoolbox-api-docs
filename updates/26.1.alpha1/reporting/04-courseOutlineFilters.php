<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\AssessmentType;

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

        /**
         * The value in this field is dependent on whether LTI is enabled and also changes ordering
         * due to json encoding, so this can't just be done with a simple SQL update
         */

        /* We're going be doing this more than a few times: prepare it only once, for speed
         */
        $updateStatement = $db->prepareQuery("
            UPDATE component_config_instance SET `value` = :value WHERE `id` = :id;
        ");

        /** Find all the component config instances for "assessmentTypes" in a course outline */
        $query = "
            SELECT cci.id, cci.value, ci.id AS component_id
            FROM component_config_instance cci
            -- must match the 'assessmentTypes' config type
            INNER JOIN component_config_definition ccd ON
                cci.component_config_definition_id = ccd.id
                AND ccd.`key` = 'assessmentTypes'
            -- must be a course outline component
            INNER JOIN component_instance ci ON cci.component_instance_id = ci.id
            INNER JOIN component_definition cd ON
                ci.component_definition_id = cd.id
                AND cd.class = 'Schoolbox\\\Learning\\\Assessment\\\Component\\\Homepage\\\Outline\\\Controller'
        ";

        $configInstances = $db->executeQuery($query)->fetchAll();

        /** Reproduce how the instance would currently configure a new component config */
        $assessmentTypeOptions = AssessmentType::getOptions();

        $ltiEnabled = $this->getConfig()->get('lti_assessments_enable');
        if (!$ltiEnabled) {
            unset($assessmentTypeOptions[AssessmentType::TYPE_LTI_ASSESSMENT]);
        }
        unset($assessmentTypeOptions[AssessmentType::TYPE_REPORTING]);

        $updated = 0;
        foreach ($configInstances as $configInstance) {
            /**
             * If all keys in the config are enabled, then it's still the default
             * The order might have changed, and LTI might not be there depending on the LTI config state over time
             */
            $currentFilters = json_decode(html_entity_decode($configInstance['value']), true);

            if ($currentFilters === null || !is_array($currentFilters)) {
                /** Couldn't decode? probably skip it */
                $this->getLogger()->debug(sprintf(
                    'Couldn\'t decode value for component config instance "%s", skipping',
                    $configInstance['id']
                ));
                continue;
            }

            /**
             * Parse the filter to only the entries that are selected
             * then compare with the original list to see if we are actually filtering out anything
             */
            $assessmentTypes = array_keys(array_filter(
                $currentFilters,
                function ($v, $k) use ($assessmentTypeOptions) {
                    return $v === "1" && key_exists($k, $assessmentTypeOptions);
                },
                ARRAY_FILTER_USE_BOTH
            ));

            if (count($assessmentTypes) < count($assessmentTypeOptions)) {
                /** This component is being filtered, skip */
                $this->getLogger()->debug(sprintf(
                    'Component instance %s (component config instance %s) is being filtered (%s); skipping',
                    $configInstance['component_id'],
                    $configInstance['id'],
                    $configInstance['value']
                ));
                continue;
            }

            $updated += 1;
            $currentFilters[AssessmentType::TYPE_REPORTING] = '1';
            $updateStatement->execute([
                'value' => json_encode($currentFilters),
                'id'    => $configInstance['id'],
            ]);

            $this->getLogger()->debug(sprintf(
                'Component instance %s was updated to include %s',
                $configInstance['component_id'],
                AssessmentType::TYPE_REPORTING,
            ));
        }

        $this->getLogger()->info(sprintf(
            '%s of %s course outline component config instances match defaults and have been updated to include Reporting Activity',
            $updated,
            count($configInstances),
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
