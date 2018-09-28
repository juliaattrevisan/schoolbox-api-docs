<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Web\Controller\Base;
use Cfg;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\GenericCollection;
use Alaress;

class Controller extends Base
{

    /** @var ConfigMapper */
    private $configMapper;

    /** @var FieldStrategyFactory */
    private $fieldRendererFactory;


    /**
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldRendererFactory
     */
    public function __construct(ConfigMapper $configMapper, FieldStrategyFactory $fieldRendererFactory)
    {
        $this->configMapper         = $configMapper;
        $this->fieldRendererFactory = $fieldRendererFactory;
    }


    /**
     * Renders an admin settings page for settings with the specified type
     *
     * @level 18.1
     * @api   GET /adminv2/setting/{typeLabel}
     *
     * @param string $typeLabel The human-friendly type label stored in lu_config_type
     * @throws \UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getSettings(string $typeLabel = null): void
    {
        $typeLabel  = $typeLabel ? urldecode($typeLabel) : 'Site';
        $typeLabels = array_column(Cfg::getTypeList() ?: [], 'type');

        if (!\in_array($typeLabel, $typeLabels, true)) {
            throw Mapper::getInstance()->generateException(Mapper::ERROR_PAGE_NOT_FOUND);
        }

        $configContext = (new ModuleConfig($this->fieldRendererFactory))->getContext(
            $this->getConfigLoader()->get('site_version'),
            $this->configMapper->findByTypeLabel($typeLabel),
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
     * @level 18.1
     * @api   POST /adminv2/setting/{typeLabel}
     *
     * @param string $typeLabel
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postSettings(string $typeLabel): void
    {
        $postedValues = $this->request->post->get();

        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->configMapper->findByColumn(ConfigMapper::COL_NAME, \array_keys($postedValues))->indexBy(ConfigMapper::COL_NAME);

        // Messaging behavior may be affected by changing configuration.
        $reRouterSettings = ['messaging_enabled', 'mail_replyto_enabled', 'feedback_url', 'feedback_text'];
        $reloadReRouter   = false;

        foreach ($postedValues as $name => $value) {
            $existingConfig  = $existingConfigs[$name];
            $valueSerializer = $this->fieldRendererFactory->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                if (\in_array($name, $reRouterSettings, true)) {
                    $reloadReRouter = true;
                }
                $existingConfig->setValue($newValue);
                $this->configMapper->save($existingConfig);
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
}
