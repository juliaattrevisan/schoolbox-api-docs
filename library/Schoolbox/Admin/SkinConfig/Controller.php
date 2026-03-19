<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress\Color\Transformer;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Crell\ApiProblem\ApiProblem;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

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
                '_routes' => [
                    'update' => $this->getRouteIfAuthorized('adminDesignSkinConfig.postConfig'),
                    'preview' => $this->getRouteIfAuthorized('adminDesignSkinConfig.previewCss'),
                ],
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
            $categoryName = $config->getSkinSubcategory()->getCategory()->getName();
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
                'customProperty' => $this->getCustomProperty($config),
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
     * Persist the submitted Skin Config payload.
     */
    public function postConfig(): void
    {
        $configs = $this->requestPost->get('configs') ?? [];
        $database = $this->getConfigMapper()->getDatabase();
        try {
            $database->beginTransaction();
            foreach ($configs as $subcategories) {
                foreach ($subcategories as $subcategory) {
                    foreach (($subcategory['options'] ?? []) as $option) {
                        if (!isset($option['id'])) {
                            continue;
                        }

                        $value = $option['value'] ?? null;

                        $database->prepareQuery(
                            'UPDATE config SET value = :value WHERE configID = :configID',
                            [
                                'configID' => (int)$option['id'],
                                'value' => is_string($value) ? $value : null,
                            ]
                        )->execute();
                    }
                }
            }
            $database->commit();
            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $database->rollBack();
            $status = Response::HTTP_BAD_REQUEST;
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($status);
            $this->response = $this->responseAsJson($problem->asArray())->withStatus($status);
        }
    }

    /**
     * Build a temporary preview stylesheet from the unsaved Skin Config payload.
     */
    public function previewCss(): void
    {
        try {
            $previewData = $this->buildPreviewData($this->requestPost->get('configs') ?? []);

            /** @var Environment $twig */
            $twig = $this->getDi()->get('templateEngine');
            $css = $twig->render('css/skin.twig', $previewData);
            $cacheControl = 'no-store, no-cache, must-revalidate, max-age=0';

            $this->response = $this->responseFromContent($css, null, 'text/css')->withHeader('Cache-Control', $cacheControl);
        } catch (\Throwable $e) {
            $status = Response::HTTP_BAD_REQUEST;
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($status);
            $this->response = $this->responseAsJson($problem->asArray())->withStatus($status);
        }
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

    /**
     * Extract the raw CSS custom property mapping from config options metadata.
     */
    private function getCustomProperty(Config $config): ?string
    {
        $options = $config->getOptions();
        if ($options === null || trim($options) === '') {
            return null;
        }
        $decoded = json_decode($options, true);
        if (!is_array($decoded)) {
            return null;
        }
        $customProperty = $decoded['customProperty'] ?? null;
        return is_string($customProperty) && $customProperty !== '' ? $customProperty : null;
    }

    /**
     * Build the Twig payload used by the preview stylesheet response.
     *
     * @param array<array-key, mixed> $configs
     * @return array{colors: array<string, array<string, mixed>>, customCss: string}
     */
    private function buildPreviewData(array $configs): array
    {
        $configOverrides = $this->extractConfigOverrides($configs);
        $previewConfigs = $this->getPreviewConfigs();
        $colorFactory = new ColorFactory();
        $transformer = new Transformer();
        $colors = [];
        foreach ($previewConfigs as $config) {
            $customProperty = $this->getCustomProperty($config);
            if ($customProperty === null) {
                continue;
            }
            $value = $configOverrides[$config->getId()] ?? $config->getValue();
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $baseColor = $colorFactory->build($value);
            $colors[$config->getName()] = $this->createColorTwigVars($baseColor, $customProperty);
            $contrastColor = $transformer->calculateTextColor($baseColor);
            $colors[$config->getName() . '_contrast'] = $this->createColorTwigVars($contrastColor, $customProperty . '-contrast');
        }
        if (isset($colors['skin_colour_body_bg']['colorObject']) && $colors['skin_colour_body_bg']['colorObject'] instanceof Color) {
            /** @var Color $bodyBackground */
            $bodyBackground = $colors['skin_colour_body_bg']['colorObject'];
            $bodyForeground = $transformer->calculateForegroundColor($bodyBackground);
            $bodyHeading = $transformer->calculateForegroundColor($bodyBackground, 0.855);
            $colors['skin_colour_body_fg'] = $this->createColorTwigVars($bodyForeground, '--body-foreground');
            $colors['skin_colour_body_h1'] = $this->createColorTwigVars($bodyHeading, '--body-foreground-h1');
        }
        if (isset($colors['skin_colour_navigation_background']['colorObject'])
            && $colors['skin_colour_navigation_background']['colorObject'] instanceof Color) {
            /** @var Color $navigationBackground */
            $navigationBackground = $colors['skin_colour_navigation_background']['colorObject'];
            $navigationForeground = $transformer->calculateForegroundColor($navigationBackground, 0.65);
            $navigationHighlight = $transformer->calculateTextColor($navigationBackground, null, $navigationForeground);
            $colors['skin_colour_navigation_foreground_highlight'] = $this->createColorTwigVars(
                $navigationHighlight,
                '--navigation-foreground-highlight'
            );
        }
        return [
            'colors' => $colors,
            'customCss' => $this->getPreviewCustomCss($previewConfigs, $configOverrides),
        ];
    }

    /**
     * @param array<array-key, mixed> $configs
     * @return array<int, string|null>
     */
    private function extractConfigOverrides(array $configs): array
    {
        $configOverrides = [];
        foreach ($configs as $subcategories) {
            if (!is_array($subcategories)) {
                continue;
            }
            foreach ($subcategories as $subcategory) {
                if (!is_array($subcategory) || !isset($subcategory['options']) || !is_array($subcategory['options'])) {
                    continue;
                }
                foreach ($subcategory['options'] as $option) {
                    if (!is_array($option) || !isset($option['id'])) {
                        continue;
                    }
                    $configOverrides[(int) $option['id']] = isset($option['value']) && is_string($option['value']) ? $option['value'] : null;
                }
            }
        }
        return $configOverrides;
    }

    /**
     * @return array<int, Config>
     */
    private function getPreviewConfigs(): array
    {
        $previewConfigs = [];
        $criteria = [
            'type_id' => ConfigMapper::TYPE_SKIN_CONFIG,
            'hidden' => 'false',
            'editable' => 'true',
        ];
        $options = [
            'order' => [
                'scc.id' => 'ASC',
                'scs.id' => 'ASC',
                'sequence' => 'ASC',
                ConfigMapper::COL_ID => 'ASC',
            ],
        ];
        foreach ($this->getConfigMapper()->findByColumnList($criteria, $options) as $config) {
            $previewConfigs[] = $config;
        }
        return $previewConfigs;
    }

    /**
     * @return array{cssVar: string, colorObject: Color, r: int, g: int, b: int, h: float, s: float, l: float, a: float}
     */
    private function createColorTwigVars(Color $color, string $cssVar): array
    {
        $hsl = $color->getAsHslArray();
        return [
            'cssVar' => $cssVar,
            'colorObject' => $color,
            'r' => $color->getRed(),
            'g' => $color->getGreen(),
            'b' => $color->getBlue(),
            'h' => $hsl['hue'],
            's' => $hsl['saturation'],
            'l' => $hsl['lightness'],
            'a' => $color->getAlpha(),
        ];
    }

    /**
     * @param array<int, Config> $previewConfigs
     * @param array<int, string|null> $configOverrides
     */
    private function getPreviewCustomCss(array $previewConfigs, array $configOverrides): string
    {
        foreach ($previewConfigs as $config) {
            if ($config->getName() !== ConfigMapper::NAME_SKIN_CUSTOM_CSS) {
                continue;
            }
            $value = $configOverrides[$config->getId()] ?? $config->getValue();
            return is_string($value) ? $value : '';
        }
        return (string) $this->getConfigLoader()->get(ConfigMapper::NAME_SKIN_CUSTOM_CSS);
    }
}
