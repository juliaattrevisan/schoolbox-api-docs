<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Redis;
use Alaress\Web\Controller\ConfigController;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate;

class Controller extends ConfigController
{
    /**
     * Renders an admin settings page for settings with the specified type
     *
     * @since 18.1
     * @api   GET /adminv2/setting/{typeLabel}
     *
     * @param string|null $typeLabel The human-friendly type label stored in lu_config_type
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws Predicate\InvalidColumnException
     */
    public function getSettings(string $typeLabel = null): void
    {
        $typeLabel  = $typeLabel ? urldecode($typeLabel) : 'Site';

        $typeLabels = $this->getConfigMapper()->findTypeList();

        if (!\in_array($typeLabel, $typeLabels, true)) {
            throw Mapper::getInstance()->generateException(Mapper::ERROR_PAGE_NOT_FOUND);
        }

        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate($typeLabel),
            $this->router->generate('settings.postSettings', ['typeLabel' => $typeLabel]),
            [ModuleConfig::CONTEXT_TYPE_LABEL => $typeLabel],
        );

        $settingsTabs = [];
        foreach ($typeLabels as $label) {
            $settingsTabs[] = [
                'label'    => $label,
                'route'    => urldecode($this->getRouter()->generate('settings.getSettings', ['typeLabel' => str_replace(' ', '+', $label)])),
                'isActive' => $typeLabel === $label,
            ];
        }

        /* Settings are now recategorised by the feature to which they are relevant
         */
        $pageCurrent = $this->router->generate('settings.postSettings', ['typeLabel' => $typeLabel]);
        $allBreadcrumbs = (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('settings.postSettings', ['typeLabel' => $typeLabel]));

        $this->getResponse()->content->set(
            $this->buildContent('@settings/settings.twig', [
                'pageCurrent'    => $pageCurrent,
                'allBreadcrumbs' => $allBreadcrumbs,
                'settingsTabs'   => $settingsTabs,
                'configContext'  => $configContext,
                'isLocalisation' => $typeLabel === 'Localisation',  // TODO replace string literals with enums
                'isSkinConfig'   => $typeLabel === 'Skin Config',   // TODO replace string literals with enums
            ])
        );
    }

    /**
     * Provides a JSON list of settings that match the provided keyword;
     * Searches on; name, title and description
     * @api /adminv2/setting/search
     */
    public function searchSettings(): void
    {
        $pb = $this->getConfigMapper()->getPredicateBuilder();
        $pb->equals('hidden', 'false')
            ->notEquals('module', 'custom');
        $foundConfig = $this->getConfigMapper()->findByPredicates($pb);

        $jsonPayload = [];

        // Configs which is in settings or has different url from the module name
        // For the other configs, the url will be /adminv2/<module name>
        $module = [
            'settings'   => '/adminv2/setting/%s',
            'comms'      => '/adminv2/news',
            'schoolData' => '/adminv2/import/schoolDataImport',
        ];

        foreach ($foundConfig as $config) {
            /** @var Config $config */
            $type = $config->getType() ? $config->getType()->getType() : '';
            $url = isset($module[$config->getModule()])
                ? sprintf($module[$config->getModule()], $type)
                : sprintf("/adminv2/%s", $config->getModule());

            $jsonPayload[] = [
                'name'          => $config->getName(),
                'title'         => $config->getTitle(),
                'description'   => $config->getDescription(),
                'module'        => $config->getModule(),
                'url'           => $url . '#' . $config->getName()
            ];
        }

        //Also include the navigation elements
        $subnav = new Subnav($this->getConfigLoader());
        $pageList = $subnav->generate();

        foreach ($pageList as $parentUrl => $parentData) {
            if (!($parentData['visible'] ?? false)) {
                continue;
            }

            // Some parent items are intended only as root nodes for the HTML
            // navigation: they are not designed to be navigable themselves.
            // Do not add these items to the searchable payload.
            if ($parentData['searchable'] ?? false) {
                $jsonPayload[] = [
                    'name'        => $parentUrl,
                    'title'       => $parentData['name'] ?? '',
                    'description' => $parentData['description'] ?? '',
                    'url'         => $parentUrl,
                ];
            }

            foreach ($parentData['children'] as $childUrl => $childData) {
                if (!($childData['visible'] ?? false)) {
                    continue;
                }
                $jsonPayload[] = [
                    'name'        => $childUrl,
                    'title'       => ($parentData['name'] ?? '') . ' › ' . ($childData['name'] ?? ''),
                    'description' => $childData['description'] ?? '',
                    'url'         => $childUrl,
                ];
            }
        }

        $this->getResponse()->content->set($this->buildJsonOutput($jsonPayload));
    }

    /**
     * @since 18.
     * @api   POST /adminv2/setting/{typeLabel}
     *
     * @param string $typeLabel
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     * @noinspection PhpUnused (web route)
     */
    public function postSettings(string $typeLabel): void
    {
        $postedValues = $this->request->post->get();

        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->getConfigMapper()->findByPredicates($this->buildConfigPredicate($typeLabel))->indexBy(ConfigMapper::COL_NAME);

        // Do any changed settings necessitate a reload of any processes?
        $reloadRouteDaemon    = false;
        $reloadScheduleDaemon = false;
        $rebuildModules       = false;
        foreach ($postedValues as $name => $value) {
            $existingConfig  = $existingConfigs[$name];
            if (!$existingConfig->getEditable()) {
                continue;
            }
            $valueSerializer = $this->getFieldStrategyFactory()->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                if (\in_array($name, ConfigMapper::SETTINGS_AFFECTING_ROUTED, true)) {
                    $reloadRouteDaemon = true;
                }
                if (\in_array($name, ConfigMapper::SETTINGS_AFFECTING_SCHEDULED, true)) {
                    $reloadScheduleDaemon = true;
                }
                if (\in_array($name, ConfigMapper::SETTINGS_AFFECTING_MODULES, true)) {
                    $rebuildModules = true;
                }
                $existingConfig->setValue(trim($newValue));
                $this->getConfigMapper()->save($existingConfig);
            }
        }

        if ($reloadRouteDaemon || $reloadScheduleDaemon) {
            $redisClient = Redis::getSignalQueueClientInstance();
            if ($redisClient && $reloadRouteDaemon) {
                $redisClient->publish('routed.' . INSTANCE_ID . '.reload', '');
            }
            if ($redisClient && $reloadScheduleDaemon) {
                $redisClient->publish('scheduled.' . INSTANCE_ID . '.refreshConfiguration', '');
            }
        }
        if ($rebuildModules) {
            // After everything else: reload the route table
            $this->getResponse()->content->set([
                'format'     => 'noContent',
                'postOutput' => [
                    function () {
                        $this->rebuilder->rebuild($this->getConfigLoader()->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));
                    }
                ],
            ]);
        }

        $route = urldecode($this->getRouter()->generate('settings.getSettings', ['typeLabel' => str_replace(' ', '+', $typeLabel)]));
        $this->getResponse()->redirect->afterPost($route);
    }


    /**
     * Returns a predicate suitable for finding config for this module
     *
     * @param string $typeLabel
     * @return Predicate\Builder
     * @throws Predicate\InvalidColumnException
     */
    private function buildConfigPredicate(string $typeLabel): Predicate\Builder
    {
        $pb = $this->getConfigMapper()
                   ->getPredicateBuilder()
                   ->equals('lct.type', $typeLabel)
                   ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_SETTINGS);

        if (!$this->getConfigLoader()->get(ConfigMapper::_BOARDER_LEAVE_ENABLED)) {
            $pb->notEquals(ConfigMapper::COL_TYPE, ConfigMapper::TYPE_BOARD);
        }
        return $pb;
    }
}
