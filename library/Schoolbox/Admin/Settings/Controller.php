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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
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

        $routeUrl = $this->getRouteUrlFromTypeLabel($typeLabel);
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

        foreach ($foundConfig as $config) {
            /** @var Config $config */
            $type = $config->getType() ? $config->getType()->getType() : '';
            $url = isset($module[$config->getModule()])
                ? sprintf($module[$config->getModule()], $type)
                : sprintf("/adminv2/%s", $config->getModule());

            if (in_array($url, $hiddenUrls)) {
                continue;
            }

            if (!$this->userHasAccessToConfigSetting($this->user, $config->getModule(), $config->getType()?->getType())) {
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

        $this->response = $this->redirectAfterPost($this->getRouteUrlFromTypeLabel($typeLabel));
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
     * Return true if the given user has access to the config settings with the given module and type.
     *
     * @param User   $user
     * @param string $module - From database `config`.`module`
     * @param ?string $type - From database `lu_config_type`.`type`
     * @return boolean
     */
    private function userHasAccessToConfigSetting(User $user, string $module, ?string $type): bool
    {
        if ($user->isSuperuser()) {
            return true;
        }

        if ($module === 'schoolData') {
            // This is for the settings in 'Admin -> School Data Import -> Home'.
            return $user->hasServicePermission(UserAccess::SERVICE_TIMETABLE, UserAccess::ACCESS_FULL);
        }

        if ($module === 'comms') {
            // This is for the settings in 'Admin -> News -> Settings / Policy'.
            return $user->hasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL);
        }

        if ($module === 'settings') {
            switch ($type) {
                case 'Resources':
                    // This is for the settings in 'Admin -> Resources -> Resource Settings'.
                    return $user->hasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL);
                case 'Skin Config':
                    // This is for the settings in 'Admin -> Design -> Skin Config'.
                    return $user->hasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL);
                case 'LMS Policy':
                    // This is for the settings in 'Admin -> LMS -> LMS Policy'.
                    return $user->hasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL);
                case 'Pastoral Care':
                    // This is for the settings in 'Admin -> Pastoral Care -> Settings / Policy'.
                    return $user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL);
                default:
                    // Do nothing.
            }
        }

        return false;
    }

    /**
     * @param string $typeLabel
     * @return string
     * @throws RouteNotFound
     */
    private function getRouteUrlFromTypeLabel(string $typeLabel): string
    {
        // Encode the spaces in the typeLabel before passing to $this->routeGenerator->generate().
        // Note replacing ' ' with '%20' is not the same as calling urlencode().  We want '%20', not '+', because
        // the typeLabel in the URL will be to the left of the '?', and the rule for URL encoding of spaces is:
        // 'Use %20 before the ? and + after'
        // https://stackoverflow.com/questions/1634271/url-encoding-the-space-character-or-20#answer-29948396
        // Example expected value for $typeLabel       : '3rd Party Services'
        // Example expected value for $typeLabelEncoded: '3rd%20Party%20Services'
        $typeLabelEncoded = str_replace(' ', '%20', $typeLabel);

        // Note a double-encoded URL will be returned here.
        // That seems to imply that when the route was registered, a decoded URL should have been used.  However, I
        // could not get matching to work without encoding the URLs when registering the routes (see Admin/Settings/Module.php).
        // So, we just have to deal with the double-encoding by decoding the returned value.
        $routeUrlDoubleEncoded = $this->routeGenerator->generate(
            'settings.getSettings' . $this->getSettingsCategoryFromTypeLabel($typeLabel),
            ['typeLabel' => $typeLabelEncoded]
        );

        // Example expected value for $routeUrlDoubleEncoded: /adminv2/setting/3rd%2520Party%2520Services
        // Example expected value for $routeUrlEncoded      : /adminv2/setting/3rd%20Party%20Services
        $routeUrlEncoded = urldecode($routeUrlDoubleEncoded);

        return $routeUrlEncoded;
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
