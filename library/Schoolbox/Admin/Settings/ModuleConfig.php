<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;

/**
 * Converts a set of config settings into a suitable format for rendering in Twig
 */
class ModuleConfig
{

    /** @var FieldStrategyFactory */
    private $fieldStrategyFactory;


    /**
     * @param FieldStrategyFactory $fieldStrategyFactory
     */
    public function __construct(FieldStrategyFactory $fieldStrategyFactory)
    {
        $this->fieldStrategyFactory = $fieldStrategyFactory;
    }


    /**
     * @param string                     $siteVersion
     * @param GenericCollection|Config[] $configs
     * @param string                     $postRoute
     * @return array
     */
    public function getContext(
        string $siteVersion,
        GenericCollection $configs,
        string $postRoute
    ): array {

        $isEditable = $hasColourPicker = $hasTimePicker = $hasHtmlEditor = $hasResourcePicker = false;

        $settings = [];
        foreach ($configs as $config) {
            if ($config->getEditable()) {
                $isEditable = true;
            }
            switch ($config->getFieldId()) {
                case ConfigMapper::FIELD_COLOUR:
                    $hasColourPicker = true;
                    break;
                case ConfigMapper::FIELD_TIME_PICKER:
                    $hasTimePicker = true;
                    break;
                case ConfigMapper::FIELD_HTML:
                    $hasHtmlEditor = true;
                    break;
                case ConfigMapper::FIELD_RESOURCE:
                    $hasResourcePicker = true;
                    break;
            }
            $settings[] = $this->fieldStrategyFactory->makeRenderer($config->getFieldId())->getTemplateAndData($config);
        }

        return [
            'siteVersion'       => $siteVersion,
            'hasColourPicker'   => $hasColourPicker,
            'hasTimePicker'     => $hasTimePicker,
            'hasHtmlEditor'     => $hasHtmlEditor,
            'hasResourcePicker' => $hasResourcePicker,
            'settings'          => $settings,
            'isEditable'        => $isEditable,
            'postRoute'         => $postRoute,
            'typeLabel'         => ''
        ];
    }
}
