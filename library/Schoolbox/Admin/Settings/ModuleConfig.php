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
    // Context options which should not be set outside of `getContext()`.
    private const CONTEXT_SITE_VERSION        = 'siteVersion';
    private const CONTEXT_HAS_COLOUR_PICKER   = 'hasColourPicker';
    private const CONTEXT_HAS_TIME_PICKER     = 'hasTimePicker';
    private const CONTEXT_HAS_HTML_EDITOR     = 'hasHtmlEditor';
    private const CONTEXT_HAS_RESOURCE_PICKER = 'hasResourcePicker';
    private const CONTEXT_SETTINGS            = 'settings';
    public const CONTEXT_ISEDITABLE          = 'isEditable';
    private const CONTEXT_POSTROUTE           = 'postRoute';

    /**
     * Context option for the type label for this config area.
     *
     * This may be set as a key in `getContext()` param `$additionalContext`.
     */
    public const CONTEXT_TYPE_LABEL = 'typeLabel';

    /**
     * Context option for whether to show the bottom drawer.
     *
     * This may be set as a key in `getContext()` param `$additionalContext`.
     */
    public const CONTEXT_USE_BOTTOM_DRAWER = 'useBottomDrawer';

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
     * @param array                      $additionalContext
     * @return array
     */
    public function getContext(
        string $siteVersion,
        GenericCollection $configs,
        string $postRoute,
        array $additionalContext
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

        return $additionalContext + [
            self::CONTEXT_SITE_VERSION        => $siteVersion,
            self::CONTEXT_HAS_COLOUR_PICKER   => $hasColourPicker,
            self::CONTEXT_HAS_TIME_PICKER     => $hasTimePicker,
            self::CONTEXT_HAS_HTML_EDITOR     => $hasHtmlEditor,
            self::CONTEXT_HAS_RESOURCE_PICKER => $hasResourcePicker,
            self::CONTEXT_SETTINGS            => $settings,
            self::CONTEXT_ISEDITABLE          => $isEditable,
            self::CONTEXT_POSTROUTE           => $postRoute,
            self::CONTEXT_TYPE_LABEL          => '',
            self::CONTEXT_USE_BOTTOM_DRAWER   => true,
        ];
    }
}
