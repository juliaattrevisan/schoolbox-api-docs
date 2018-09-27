<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Web\Controller\Base;
use Alaress_Byte_Format;
use Cfg;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;

class Controller extends Base
{

    /** @var ConfigMapper */
    private $configMapper;

    /** @var FieldRendererFactory */
    private $fieldRendererFactory;


    /**
     * @param ConfigMapper         $configMapper
     * @param FieldRendererFactory $fieldRendererFactory
     */
    public function __construct(ConfigMapper $configMapper, FieldRendererFactory $fieldRendererFactory)
    {
        $this->configMapper         = $configMapper;
        $this->fieldRendererFactory = $fieldRendererFactory;
    }


    /**
     * @param string $typeLabel The human-friendly type label stored in lu_config_type
     * @throws \UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    public function getSettings(string $typeLabel = null): void
    {
        $typeLabel = $typeLabel ? urldecode($typeLabel) : 'Site';

        $hasTimePicker = false;
        $isEditable    = false;

        $configs = $this->configMapper->findByTypeLabel($typeLabel);
        if (!$configs->count()) {
            // TODO check in module, explode all types to distinct routes, or generate pretty error here
            $this->getResponse()->status->setCode(404);
            return;
        }
        $settings = [];
        foreach ($configs as $config) {
            if (ConfigMapper::FIELD_TIME_PICKER === $config->getFieldId()) {
                $hasTimePicker = true;
            }
            if ($config->getEditable()) {
                $isEditable = true;
            }
            $renderer   = $this->fieldRendererFactory->make($config);
            $settings[] = [
                'template' => $renderer->getTemplate(),
                'data'     => $renderer->getData(),
            ];
        }


        $typeLabels   = Cfg::getTypeList();
        $settingsTabs = [];
        if (\is_array($typeLabels)) {
            foreach ($typeLabels as $aType) {
                $settingsTabs[] = [
                    'label'    => $aType['type'],
                    'route'    => urldecode($this->getRouter()->generate('settings.getSettings', ['typeLabel' => str_replace(' ', '+', $aType['type'])])),
                    'isActive' => $typeLabel === $aType['type'],
                ];
            }
        }

        $this->getResponse()->content->set(
            $this->buildContent('@settings/settings.twig', [
                'siteVersion'    => $this->getConfigLoader()->get('site_version'),
                'hasTimePicker'  => $hasTimePicker,
                'pageCurrent'    => 'setting',
                'settingsTabs'   => $settingsTabs,
                'isLocalisation' => $typeLabel === 'Localisation',  // TODO replace string literals with enums
                'isSkinConfig'   => $typeLabel === 'Skin Config',   // TODO replace string literals with enums
                'settings'       => $settings,
                'isEditable'     => $isEditable,
            ]));
    }


    /**
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postSettings(): void
    {
        $postedValues = $this->request->post->get();

        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->configMapper->findByColumn($this->configMapper::COL_NAME, \array_keys($postedValues))->indexBy($this->configMapper::COL_NAME);

        // Messaging behavior may be affected by changing configuration.
        $reRouterSettings = ['messaging_enabled', 'mail_replyto_enabled', 'feedback_url', 'feedback_text'];
        $reloadReRouter   = false;

        foreach ($postedValues as $name => $value) {
            $existingConfig = $existingConfigs[$name];
            switch ($existingConfig->getFieldId()) {
                case ConfigMapper::FIELD_BYTES:
                    $newValue = Alaress_Byte_Format::stringToBytes($value);
                    break;
                case ConfigMapper::FIELD_FILE:
                    $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();
                    try {
                        $result   = $factory->build($value)->getFirst();
                        $newValue = $result->getHash();
                    } catch (\Schoolbox\Resource\Exception $e) {
                        // File is empty or not found. Delete the record completely
                        $newValue = '';
                    } catch (\InvalidArgumentException $e) {
                        $newValue = '';
                    }
                    break;
                case ConfigMapper::FIELD_FILE_MULTI:
                    $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();
                    try {
                        $newValue = '';
                        if (is_array($value)) {
                            foreach ($value as $file) {
                                $image = $factory->build([$file])->getFirst();
                                if ($newValue !== '') {
                                    $newValue .= ',';
                                }
                                $newValue .= $image->getHash();
                            }
                        }
                    } catch (\Schoolbox\Resource\Exception $e) {
                        // File is empty or not found. Delete the record completely
                        $newValue = '';
                    } catch (\InvalidArgumentException $e) {
                        $newValue = '';
                    }
                    break;
                default:
                    $newValue = $value;
            }

            if ($newValue != $existingConfig->getValue()) {
                if (\in_array($name, $reRouterSettings, true)) {
                    $reloadReRouter = true;
                }
                $existingConfig->setValue($newValue);
                $this->configMapper->save($existingConfig);
            }
        }

        // Reload message router
        if ($reloadReRouter) {
            \Alaress\Redis::getClientInstance()->publish('routed.' . INSTANCE_ID . '.reload', '');
        }

        // TODO do something a bit more elegant, probably disable form and AJAX it
        /** @var \SitePage $page */
        $page = $this->getDi()->get('sitePage');
        $page->backToTheFuture(1);
    }
}
