<?php

namespace Schoolbox\Admin\Plan;

use Alaress\Module\Cache\Rebuilder;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Mapper\Exception\NotFound;
use Symfony\Component\Yaml\Yaml;
use Schoolbox\Config\ConfigMapper;

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

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null; // Does not matter if we run this as root
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
            $this->rebuildModuleCache();
        } else {
            echo
            <<<HELP
Updates instance dataset to reflect the appropriate features available for the plan provided

Usage: set-plan.php --plan [portal,portalplus,basic,pro,elite]

HELP
            ;
            exit;
        }
    }

    /**
     * Updates the config values specified in the plans config
     *
     * @param array $configList
     */
    private function setConfig(array $configList)
    {
        $configMapper = ConfigMapper::getInstance();

        foreach ($configList as $configName => $item) {
            $this->getLogger()->info("Setting config for '$configName'");

            try {
                $config = $configMapper->findOneByColumn(ConfigMapper::COL_NAME, $configName);

                if (isset($item['hide'])) {
                    $config->setHidden($item['hide']);
                }

                if (isset($item['edit'])) {
                    $config->setEditable($item['edit']);
                }

                if (isset($item['value'])) {
                    $config->setValue($item['value']);
                }

                $configMapper->save($config);
            } catch (NotFound $e) {
                $this->getLogger()->warning("Setting '$configName' not found");
            }
        }
    }

    /**
     * Add and remove elements to the top menu based on the plan being run by the school
     *
     * @param array $menu
     */
    private function setTopMenu(array $menu)
    {
        if (isset($menu['hide'])) {
            // Hide any menu items that match the hide URL string
            $statement = $this->getDatabaseInternal()->prepareQuery(
                "
            UPDATE menu_top SET plan_hide = 1 WHERE url like :url AND plan_hide = 0
        "
            );
            foreach ($menu['hide'] as $url) {
                $this->getLogger()->info("Disabling menu items containing the url: '$url'");
                $statement->execute(['url' => $url . '%']);
            }
        }

        if (isset($menu['show'])) {
            // Undelete any menu items that match the show string
            $statement = $this->getDatabaseInternal()->prepareQuery(
                "
                UPDATE menu_top SET plan_hide = 0 WHERE url like :url AND plan_hide = 1
            "
            );
            foreach ($menu['show'] as $url) {
                $this->getLogger()->info("Enabling menu items containing the url: '$url'");
                $statement->execute(['url' => $url . '%']);
            }
        }
    }

    /**
     * Show and hide components
     * @param array $components
     */
    private function setComponents(array $components)
    {

        $this->getDatabaseInternal()->beginTransaction();

        try {
            $this->getLogger()->info("Enabling all components");
            // Enable all components
            $statement = $this->getDatabaseInternal()->prepareQuery(
                "
                UPDATE component_definition SET enabled=TRUE
            "
            );
            $statement->execute();

            // Disable all the components that are not meant to be available
            $statement = $this->getDatabaseInternal()->prepareQuery(
                "
                UPDATE component_definition SET enabled=FALSE, visible='false' WHERE class = :component
            "
            );
            if (isset($components['hide'])) {
                foreach ($components['hide'] as $component) {
                    $this->getLogger()->info("Disabling the component: '$component' ");
                    $statement->execute(['component' => $component]);
                }
            }
            $this->getDatabaseInternal()->commit();
        } catch (\PDOException $e) {
            $this->getDatabaseInternal()->rollBack();
        }
    }

    /**
     * After all is done: rebuild the module and route caches.
     *
     * Rebuilding the module and route caches is necessary because changing the
     * plan may change the routes which are available to an instance.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function rebuildModuleCache(): void
    {
        $this->getLogger()->info("Rebuilding route and module cache");
        /** @var Rebuilder $cacheBuilder */
        $cacheBuilder = $this->getDi()->get('moduleCache.rebuilder');
        $cacheBuilder->rebuild($this->getConfig()->get('site_school_code'));
    }
}
