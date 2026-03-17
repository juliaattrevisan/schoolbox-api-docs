<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    /**
     * Handles the GET request to display the skin configuration page in the admin interface.
     *
     * This method retrieves the current skin configuration options, organizes them by category and subcategory,
     * and renders the appropriate Twig template with the necessary data for display.
     */
    public function getConfig(): void
    {
        $pageCurrent = $this->routeGenerator->generate('adminDesignSkinConfig.getConfig');

        $configs = $this->viewConfig();

        $this->response = $this->responseFromTemplate(
            '@adminDesignSkinConfig/index.twig',
            [
                'pageTitle' => 'Skin Config',
                'pageCurrent'    => $pageCurrent,
                'configs' => $configs,
                'allBreadcrumbs' => (new Subnav(
                    $this->getConfigLoader(),
                    $this->getUser()
                ))->generateBreadcrumbs($this->routeGenerator->generate('adminDesignSkinConfig.getConfig')),
            ]
        );
    }

    /**
     * Retrieves skin configuration options, grouped by category and subcategory, for rendering in the admin interface.
     *
     * @return array An associative array structured as [category][subcategory][] of config options.
     */
    private function viewConfig(): array
    {
        $skinConfigs = $this
            ->getConfigMapper()
            ->findByColumnList(
                [
                    'type_id' => ConfigMapper::TYPE_SKIN_CONFIG,
                    'hidden' => 'false',
                    'editable' => 'true',
                ],
                [
                    'order' => [
                        'scc.id' => 'ASC',
                        'scs.id' => 'ASC',
                        'sequence' => 'ASC',
                        ConfigMapper::COL_ID => 'ASC',
                    ],
                ]
            );

        /* Converting to JSON format
         */
        $data = $skinConfigs->reduce(function (array $carry, Config $config): array {
            $categoryName = strtolower($config->getSkinSubcategory()->getCategory()->getName());
            $subcategoryId = $config->getSkinSubcategory()->getId();
            $subcategoryName = $config->getSkinSubcategory()->getName();

            // Create subcategory bucket once
            $carry[$categoryName][$subcategoryId] ??= [
                'name' => $subcategoryName,
                'options' => [],
            ];

            $carry[$categoryName][$subcategoryId]['options'][] = [
                'id' => $config->getId(),
                'name' => $config->getName(),
                'value' => $config->getValue(),
                'title' => $config->getTitle(),
                'description' => $config->getDescription(),
                'type' => $this->getOptionType($config),
                'editable' => $config->getEditable(),
                'hidden' => $config->getHidden(),
            ];

            return $carry;
        }, []);

        // Convert subcategory maps to indexed arrays for JSON output while preserving DB order.
        foreach ($data as $category => $subcategoryMap) {
            $data[$category] = array_values($subcategoryMap);
        }

        return $data;
    }

    /**
     * Map the config field type to the frontend Skin Config input type.
     */
    private function getOptionType(Config $config): string
    {
        return match ($config->getFieldId()) {
            ConfigMapper::FIELD_TEXT_AREA => 'textarea',
            ConfigMapper::FIELD_COLOUR => 'colour',
            default => 'colour',
        };
    }
}
