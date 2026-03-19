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
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class Helper
{
    public function __construct(
        private ConfigMapper $configMapper,
        private IConfigLoader $configLoader,
    ) {
    }

    /**
     * Build the Twig payload used by the preview stylesheet response.
     *
     * @param array<array-key, mixed> $configs
     * @return array{colors: array<string, array<string, mixed>>, customCss: string}
     */
    public function buildPreviewData(array $configs): array
    {
        return $this->buildTwigData($this->extractConfigOverrides($configs));
    }

    /**
     * Build the Twig payload used by the persisted skin stylesheet response.
     *
     * @return array{colors: array<string, array<string, mixed>>, customCss: string}
     */
    public function buildPersistedData(): array
    {
        return $this->buildTwigData([]);
    }

    /**
     * @param array<int, string|null> $configOverrides
     * @return array{colors: array<string, array<string, mixed>>, customCss: string}
     */
    private function buildTwigData(array $configOverrides): array
    {
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
        foreach ($this->configMapper->findByColumnList($criteria, $options) as $config) {
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
        return (string) $this->configLoader->get(ConfigMapper::NAME_SKIN_CUSTOM_CSS);
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
}
