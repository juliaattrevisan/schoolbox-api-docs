<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Redis;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate;
use Twig\Error\Error;

class Controller extends ConfigController
{
    /**
     * Renders an admin settings page for settings with the specified type
     *
     * @param string|null $typeLabel The human-friendly type label stored in lu_config_type
     * @throws RouteNotFound
     * @throws Exception
     * @throws Predicate\InvalidColumnException|Error
     * @since 18.1
     * @api   GET /adminv2/setting/{typeLabel}
     */
    public function getSettings(string $typeLabel = null): void
    {
        $typeLabel  = $typeLabel ? urldecode($typeLabel) : 'Site';

        $typeLabels = $this->getConfigMapper()->findTypeList();

        if (!\in_array($typeLabel, $typeLabels, true)) {
            throw Mapper::getInstance()->generateException(Mapper::ERROR_PAGE_NOT_FOUND);
        }

        $settingsCategory = $this->getSettingsCategoryFromTypeLabel($typeLabel);
        // NOTE: $typeLabel is not passed to routeGenerator->generate() to avoid regex match error
        //       E.g. "Parameter value for [typeLabel] did not match the regex `LMS%20Policy`"
        $routeUrlTemplate = $this->routeGenerator->generate("settings.postSettings$settingsCategory");
        $routeUrl = str_replace('{typeLabel}', $typeLabel, $routeUrlTemplate);
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate($typeLabel),
            $routeUrl,
            [ModuleConfig::CONTEXT_TYPE_LABEL => $typeLabel],
        );

        $settingsTabs = [];
        foreach ($typeLabels as $label) {
            $settingsTabs[] = [
                'label'    => $label,
                'route'    => urldecode($this->routeGenerator->generate('settings.getSettings', ['typeLabel' => str_replace(' ', '+', $label)])),
                'isActive' => $typeLabel === $label,
            ];
        }

        /* Settings are now recategorised by the feature to which they are relevant
         */
        $allBreadcrumbs = (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($routeUrl);

        $this->response = $this->responseDependentOnRequest(
            '@settings/settings.twig',
            [
                'pageCurrent'    => $routeUrl,
                'allBreadcrumbs' => $allBreadcrumbs,
                'settingsTabs'   => $settingsTabs,
                'configContext'  => $configContext,
                'isLocalisation' => $typeLabel === 'Localisation',  // TODO replace string literals with enums
                'isSkinConfig'   => $typeLabel === 'Skin Config',   // TODO replace string literals with enums
            ]
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

        //Also include the navigation elements
        $subnav = new Subnav($this->getConfigLoader(), $this->getUser());
        $pageList = $subnav->generate();

        $hiddenUrls = [];
        foreach ($pageList as $parentUrl => $parentData) {
            if (!($parentData['visible'] ?? false)) {
                $hiddenUrls[] = $parentUrl;
                continue;
            }

            // Some parent items are intended only as root nodes for the HTML
            // navigation: they are not designed to be navigable themselves.
            // Do not add these items to the searchable payload.
            if ($parentData['searchable'] ?? $parentData['visible'] ?? false) {
                $jsonPayload[] = [
                    'name'        => $parentUrl,
                    'title'       => $parentData['name'] ?? '',
                    'description' => $parentData['description'] ?? '',
                    'url'         => $parentUrl,
                ];
            }

            foreach ($parentData['children'] as $childUrl => $childData) {
                if (!($childData['visible'] ?? false)) {
                    $hiddenUrls[] = $childUrl;
                    continue;
                }
                if ($childData['searchable'] ?? $childData['visible'] ?? false) {
                    $jsonPayload[] = [
                        'name'        => $childUrl,
                        'title'       => ($parentData['name'] ?? '') . ' › ' . ($childData['name'] ?? ''),
                        'description' => $childData['description'] ?? '',
                        'url'         => $childUrl,
                    ];
                }
            }
        }

        // Add config settings to the searchable payload only for superusers.
        // Non-superusers who have access to the Admin system (e.g. News Moderators) will see only a small subset of
        // pages, so they should be able to find what they're looking for just by inspecting the few admin pages to
        // which they have access.
        if ($this->user->isSuperuser()) {
            foreach ($foundConfig as $config) {
                /** @var Config $config */
                $type = $config->getType() ? $config->getType()->getType() : '';
                $url = isset($module[$config->getModule()])
                    ? sprintf($module[$config->getModule()], $type)
                    : sprintf("/adminv2/%s", $config->getModule());

                if (in_array($url, $hiddenUrls)) {
                    continue;
                }

                $jsonPayload[] = [
                    'name'          => $config->getName(),
                    'title'         => $config->getTitle(),
                    'description'   => $config->getDescription(),
                    'module'        => $config->getModule(),
                    'url'           => $url . '#' . $config->getName()
                ];
            }
        }

        $this->response = $this->responseAsJson($jsonPayload);
    }

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     * @noinspection PhpUnused (web route)
     * @since 18.
     * @api   POST /adminv2/setting/{typeLabel}
     */
    public function postSettings(string $typeLabel): void
    {
        $postedValues = $this->requestPost->getArrayCopy();

        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->getConfigMapper()->findByPredicates($this->buildConfigPredicate($typeLabel))->indexBy(ConfigMapper::COL_NAME);

        // Do any changed settings necessitate a reload of any processes?
        $reloadRouteDaemon    = false;
        $reloadScheduleDaemon = false;
        $rebuildModules       = false;
        $clearOidcCache       = false;

        foreach ($postedValues as $name => $value) {
            $existingConfig  = $existingConfigs[$name];

            if (!$existingConfig->getEditable()) {
                continue;
            }

            $valueSerializer = $this->getFieldStrategyFactory()->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value, $existingConfig);

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
                if (\in_array($name, ConfigMapper::SETTINGS_AFFECTING_OAUTH, true)) {
                    $clearOidcCache = true;
                }
                $existingConfig->setValue(trim($newValue));
                $this->getConfigMapper()->save($existingConfig);
            }
        }

        if ($reloadRouteDaemon || $reloadScheduleDaemon) {
            $redisClient = Redis::getSignalQueueClientInstance();
            if ($redisClient && $reloadRouteDaemon) {
                $redisClient->publish('scheduled.' . INSTANCE_ID . '.reload', '');
            }
            if ($redisClient && $reloadScheduleDaemon) {
                $redisClient->publish('scheduled.' . INSTANCE_ID . '.refreshConfiguration', '');
            }
        }
        if ($rebuildModules) {
            $this->postOutputCallbacks[] = $this->buildRebuildModuleCacheCallback();
            // After everything else: reload the route table
            $this->response = $this->responseEmpty();
        }
        if ($clearOidcCache) {
            $this->oidcCache->clear();
        }

        $settingsCategory = $this->getSettingsCategoryFromTypeLabel($typeLabel);
        $routeUrlTemplate = $this->routeGenerator->generate("settings.getSettings$settingsCategory");
        $routeUrl = str_replace('{typeLabel}', $typeLabel, $routeUrlTemplate);
        $routeUrlDecoded = urldecode($routeUrl);

        $this->response = $this->redirectAfterPost($routeUrlDecoded);
    }

    // Functions for routes accessible by Pastoral Moderators --------------------------------------------------------//

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSettingsPastoral(string $typeLabel): void
    {
        $this->getSettings($typeLabel);
    }

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postSettingsPastoral(string $typeLabel): void
    {
        $this->postSettings($typeLabel);
    }

    // Functions for routes accessible by LMS Moderators -------------------------------------------------------------//

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSettingsLms(string $typeLabel): void
    {
        $this->getSettings($typeLabel);
    }

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postSettingsLms(string $typeLabel): void
    {
        $this->postSettings($typeLabel);
    }

    // Functions for routes accessible by CMS Moderators -------------------------------------------------------------//

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSettingsCms(string $typeLabel): void
    {
        $this->getSettings($typeLabel);
    }

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postSettingsCms(string $typeLabel): void
    {
        $this->postSettings($typeLabel);
    }

    // Functions for routes accessible by Design Moderators ----------------------------------------------------------//

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSettingsDesign(string $typeLabel): void
    {
        $this->getSettings($typeLabel);
    }

    /**
     * @param string $typeLabel
     * @throws RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postSettingsDesign(string $typeLabel): void
    {
        $this->postSettings($typeLabel);
    }

    // Private functions ---------------------------------------------------------------------------------------------//

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

        return $pb;
    }

    /**
     * @param string $typeLabel
     * @return string
     */
    private function getSettingsCategoryFromTypeLabel(string $typeLabel): string
    {
        return match ($typeLabel) {
            'Pastoral Care' => 'Pastoral',
            'Document Management',
            'LMS Policy' => 'Lms',
            'Resources' => 'Cms',
            'Skin Config' => 'Design',
            default => '',
        };
    }
}
