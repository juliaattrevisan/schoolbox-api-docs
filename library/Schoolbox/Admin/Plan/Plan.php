<?php

namespace Schoolbox\Admin\Plan;

use Schoolbox\Application\CliTool;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Symfony\Component\Yaml\Yaml;

/**
 * CLI tool for setting the plan of a server
 */
class Plan extends CliTool\App
{

    public function __construct()
    {
        $this->configMapper = ConfigMapper::getInstance();
    }

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['plan:'];
    }

    public function run(): void
    {
        $plan = $this->getOpt('plan');

        if ($plan) {
            // Setting the plan for a single instance
            $planConfigFile = 'config/' . $plan . 'yaml';

            if (is_file($planConfigFile) && is_readable($planConfigFile)) {
                $planConfig = Yaml::parseFile('config/' . $plan . 'yaml');
            } else {
                $this->getLogger()->error("The plan '$plan' is not an available configuration, check the config exists")
                exit(1);
            }

            $this->setConfig($planConfig['config']);
            $this->setTopMenu($planConfig['topMenu']);
            $this->setComponents($planConfig['components']);
        } else {
            // Setting plans for all instances

        }
    }

    /**
     * Updates the config values specified in the plans config
     *
     * @param array $configList
     */
    private function setConfig(array $configList)
    {
        $statement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE config SET value = :value, hidden=:hidden WHERE name = :name
        ");
        foreach ($configList as $configName => $value) {
            $statement->execute(['name' => $configName, 'value' => $value]);
        }
    }

    /**
     * Add and remove elements to the top menu based on the plan being run by the school
     *
     * @param array $menu
     */
    private function setTopMenu(array $menu)
    {
        $statement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE menu_top SET deleted_at = NOW() WHERE url like :url.'%' AND deleted_at IS NULL
        ");
        foreach ($menu['hide'] as $url) {
            $statement->execute(['url' => $url]);
        }
    }

    /**
     * Show and hide components
     * @param array $components
     */
    private function setComponents(array $components)
    {
        $statement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE component_definition SET enabled='false' WHERE class = :component
        ");
        foreach ($components['hide'] as $component) {
            $statement->execute(['component' => $component]);
        }
    }
}
