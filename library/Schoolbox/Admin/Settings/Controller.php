<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Web\Controller\ConfigController;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\GenericCollection;
use Alaress;
use Alaress\Database\Predicate;

class Controller extends ConfigController
{

    /**
     * Renders an admin settings page for settings with the specified type
     *
     * @since 18.1
     * @api   GET /adminv2/setting/{typeLabel}
     *
     * @param string $typeLabel The human-friendly type label stored in lu_config_type
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
            $this->router->generate('settings.postSettings', ['typeLabel' => $typeLabel])
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
        echo $typeLabel;

        $pageCurrent = '';
        $subpageCurrent = '';

        switch ($typeLabel) {
            case 'Document Management':
                $pageCurrent = Subnav::PARENT_LMS;
                $subpageCurrent = Subnav::CHILD_LMS_DOCUMENT_MANAGEMENT;
                break;

            case 'LMS Policy':
                $pageCurrent = Subnav::PARENT_LMS;
                $subpageCurrent = Subnav::CHILD_LMS_POLICY;
                break;

            case 'Modules':
                $pageCurrent = Subnav::PARENT_MODULES;
                break;

            case 'Board Leave':
                $pageCurrent = Subnav::PARENT_MODULES;
                $subpageCurrent = Subnav::CHILD_MODULES_BOARD_LEAVE;
                break;

            case 'Pastoral Care':
                $pageCurrent = Subnav::PARENT_MODULES;
                $subpageCurrent = Subnav::CHILD_MODULES_PASTORAL_CARE;
                break;

            case 'Skin Config':
                $pageCurrent = Subnav::PARENT_PERSONALISATION;
                $subpageCurrent = Subnav::CHILD_PERSONALISATION_SKIN_CONFIG;
                break;

            case 'Resources':
                $pageCurrent = Subnav::PARENT_RESOURCES;
                break;

            case '3rd Party Services':
            case 'Debugging':
            case 'Email':
            case 'External DB':
            case 'LDAP':
            case 'Localisation':
            case 'Paths':
            case 'Policy':
            case 'SAML':
            case 'Security':
            case 'Site':
                $pageCurrent = Subnav::PARENT_SYSTEM_SETTINGS;
                break;

            default:
                $pageCurrent = 'setting';
                break;
        }

        $this->getResponse()->content->set(
            $this->buildContent('@settings/settings.twig', [
                'pageCurrent'    => $pageCurrent,
                'subpageCurrent' => $subpageCurrent,
                'settingsTabs'   => $settingsTabs,
                'configContext'  => $configContext,
                'isLocalisation' => $typeLabel === 'Localisation',  // TODO replace string literals with enums
                'isSkinConfig'   => $typeLabel === 'Skin Config',   // TODO replace string literals with enums
            ])
        );
    }


    /**
     * @since 18.1
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

        // Messaging behavior may be affected by changing configuration.
        $reRouterSettings = ['messaging_enabled', 'mail_replyto_enabled', 'feedback_url', 'feedback_text'];
        $reloadReRouter   = false;

        $notifierSettings = [
            'restrict_parent_duework_access',
            'restrict_student_submissions',
            'restrict_parent_grades',
        ];
        $dropNotifierConfigCache = false;

        foreach ($postedValues as $name => $value) {
            $existingConfig  = $existingConfigs[$name];
            if (!$existingConfig->getEditable()) {
                continue;
            }
            $valueSerializer = $this->getFieldStrategyFactory()->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                if (\in_array($name, $reRouterSettings, true)) {
                    $reloadReRouter = true;
                }
                if (\in_array($name, $notifierSettings, true)) {
                    $dropNotifierConfigCache = true;
                }
                $existingConfig->setValue(trim($newValue));
                $this->getConfigMapper()->save($existingConfig);
            }
        }

        if ($reloadReRouter || $dropNotifierConfigCache) {
            $redisClient = Alaress\Redis::getClientInstance();
            if ($redisClient && $reloadReRouter) {
                // Reload message router
                $redisClient->publish('routed.' . INSTANCE_ID . '.reload', '');
            }
            if ($redisClient && $dropNotifierConfigCache) {
                $redisClient->publish('scheduled.' . INSTANCE_ID . '.refreshConfiguration', '');
            }
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
