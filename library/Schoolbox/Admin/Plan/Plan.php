<?php

namespace Schoolbox\Admin\Plan;

use Schoolbox\Application\CliTool;
use Symfony\Component\Yaml\Yaml;

/**
 * CLI tool for setting the plan of a server
 */
class Plan extends CliTool\App
{

    private const OPT_LONG_PLAN = 'plan:';

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_PLAN];
    }

    public function run(): void
    {
        $plan = $this->getOpt(self::OPT_LONG_PLAN);

        if ($plan) {
            // Setting the plan for a single instance
            $planConfigFile = __DIR__ . '/config/' . $plan . '.yaml';

            if (is_file($planConfigFile) && is_readable($planConfigFile)) {
                $planConfig = Yaml::parseFile($planConfigFile);
            } else {
                $this->getLogger()->error("The plan '$plan' is not an available configuration, check the config exists");
                exit(1);
            }

            $this->getLogger()->info("Setting plan to '$plan'");

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
            UPDATE config SET value = :value WHERE name = :name
        ");
        foreach ($configList as $configName => $value) {
            $this->getLogger()->info("Setting config '$configName' to '$value'");
            $statement->execute(['name' => $configName, 'value' => $value ]);
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
            UPDATE menu_top SET deleted_at = NOW() WHERE url like :url AND deleted_at IS NULL
        ");
        foreach ($menu['hide'] as $url) {
            $this->getLogger()->info("Disabling menu items containing the url: '$url'");
            $statement->execute(['url' => $url . '%']);
        }
    }

    /**
     * Show and hide components
     * @param array $components
     */
    private function setComponents(array $components)
    {

        $this->getLogger()->info("Enabling all components");
        // Enable all components
        $statement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE component_definition SET enabled='true'
        ");
        $statement->execute();

        // Disable all the components that are not meant to be available
        $statement = $this->getDatabaseInternal()->prepareQuery("
            UPDATE component_definition SET enabled='false', visible='false' WHERE class = :component
        ");
        foreach ($components['hide'] as $component) {
            $this->getLogger()->info("Disabling the component: '$component' ");
            $statement->execute(['component' => $component]);
        }
    }
}
