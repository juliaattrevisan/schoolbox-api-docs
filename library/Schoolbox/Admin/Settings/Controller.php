<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Web\Controller\ConfigController;
use Cfg;
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

        $typeLabels = $this->getConfigMapper()->findTypeList($this->getConfigLoader()->get('boarder_leave_enabled'));

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

        $this->getResponse()->content->set(
            $this->buildContent('@settings/settings.twig', [
                'pageCurrent'    => 'setting',
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
     */
    public function postSettings(string $typeLabel): void
    {
        $postedValues = $this->request->post->get();

        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->getConfigMapper()->findByPredicates($this->buildConfigPredicate($typeLabel))->indexBy(ConfigMapper::COL_NAME);

        // Messaging behavior may be affected by changing configuration.
        $reRouterSettings = ['messaging_enabled', 'mail_replyto_enabled', 'feedback_url', 'feedback_text'];
        $reloadReRouter   = false;

        foreach ($postedValues as $name => $value) {
            $existingConfig  = $existingConfigs[$name];
            $valueSerializer = $this->getFieldStrategyFactory()->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                if (\in_array($name, $reRouterSettings, true)) {
                    $reloadReRouter = true;
                }
                $existingConfig->setValue(trim($newValue));
                $this->getConfigMapper()->save($existingConfig);
            }
        }

        // Reload message router
        if ($reloadReRouter) {
            $redisClient = Alaress\Redis::getClientInstance();
            if ($redisClient) {
                $redisClient->publish('routed.' . INSTANCE_ID . '.reload', '');
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
