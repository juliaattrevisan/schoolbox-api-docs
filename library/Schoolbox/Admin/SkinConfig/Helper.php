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
use OzdemirBurak\Iris\Color\Hexa;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class Helper
{
    private const string NAME_SONAR_SKIN_CUSTOM_CSS = 'skin_sonar_custom_css';

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
        $typeId = $this->resolvePreviewTypeId($this->extractPostedToggleEnabled($configs));

        return $this->buildTwigData(
            $this->extractConfigOverrides($this->extractPostedConfigSection($configs, $typeId)),
            $typeId,
        );
    }

    /**
     * Build the Twig payload used by the persisted skin stylesheet response.
     *
     * @return array{colors: array<string, array<string, mixed>>, customCss: string}
     */
    public function buildPersistedData(): array
    {
        return $this->buildTwigData([], $this->resolvePreviewTypeId($this->isPersistedSonarEnabled()));
    }

    /**
     * @param array<int, string|null> $configOverrides
     * @return array{colors: array<string, array<string, mixed>>, customCss: string}
     */
    private function buildTwigData(array $configOverrides, int $typeId): array
    {
        $previewConfigs = $this->getPreviewConfigs($typeId);
        $colorFactory = new ColorFactory();
        $transformer = new Transformer();
        $colors = [];
        foreach ($previewConfigs as $config) {
            if ($this->shouldOmitFromStylesheet($config)) {
                continue;
            }

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
        if (
            isset($colors['skin_colour_navigation_background']['colorObject'])
            && $colors['skin_colour_navigation_background']['colorObject'] instanceof Color
        ) {
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
            'customCss' => $this->getPreviewCustomCss($previewConfigs, $configOverrides, $typeId),
        ];
    }

    /**
     * @param array<array-key, mixed> $configs
     * @return array<array-key, mixed>
     */
    private function extractPostedConfigSection(array $configs, int $typeId): array
    {
        if (!array_key_exists('existing', $configs) && !array_key_exists('sonar', $configs)) {
            return $configs;
        }

        $sectionKey = $typeId === ConfigMapper::TYPE_SONAR_SKIN_CONFIG ? 'sonar' : 'existing';
        $section = $configs[$sectionKey] ?? [];

        return is_array($section) ? $section : [];
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
    private function getPreviewConfigs(int $typeId): array
    {
        $previewConfigs = [];
        $criteria = [
            'type_id' => $typeId,
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
        $hsl = (new Hexa($color->getAsHexAlpha()))->toHsla();
        return [
            'cssVar' => $cssVar,
            'colorObject' => $color,
            'r' => $color->getRed(),
            'g' => $color->getGreen(),
            'b' => $color->getBlue(),
            'h' => $hsl->hue(),
            's' => $hsl->saturation(),
            'l' => $hsl->lightness(),
            'a' => $color->getAlpha(),
        ];
    }

    /**
     * @param array<int, Config> $previewConfigs
     * @param array<int, string|null> $configOverrides
     */
    private function getPreviewCustomCss(array $previewConfigs, array $configOverrides, int $typeId): string
    {
        $customCssConfigName = $this->getCustomCssConfigName($typeId);

        foreach ($previewConfigs as $config) {
            if ($config->getName() !== $customCssConfigName) {
                continue;
            }
            $value = $configOverrides[$config->getId()] ?? $config->getValue();
            return is_string($value) ? $value : '';
        }

        return (string) $this->configLoader->get($customCssConfigName);
    }

    /**
     * @param array<array-key, mixed> $configs
     */
    private function extractPostedToggleEnabled(array $configs): ?bool
    {
        $toggle = $configs['toggle'] ?? null;
        if (!is_array($toggle) || !array_key_exists('enabled', $toggle)) {
            return null;
        }

        return filter_var($toggle['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function isPersistedSonarEnabled(): bool
    {
        return filter_var((string) $this->configLoader->get(ConfigMapper::NAME_SONAR_UI_TOGGLE), FILTER_VALIDATE_BOOLEAN);
    }

    private function resolvePreviewTypeId(?bool $sonarEnabled): int
    {
        return $sonarEnabled ? ConfigMapper::TYPE_SONAR_SKIN_CONFIG : ConfigMapper::TYPE_SKIN_CONFIG;
    }

    private function getCustomCssConfigName(int $typeId): string
    {
        return $typeId === ConfigMapper::TYPE_SONAR_SKIN_CONFIG
            ? self::NAME_SONAR_SKIN_CUSTOM_CSS
            : ConfigMapper::NAME_SKIN_CUSTOM_CSS;
    }

    private function shouldOmitFromStylesheet(Config $config): bool
    {
        $categoryName = $config->getSkinSubcategory()?->getCategory()?->getName();
        if (!is_string($categoryName) || trim($categoryName) === '') {
            return false;
        }

        return in_array(
            strtolower($categoryName),
            [
                ConfigMapper::NAME_SKIN_CATEGORY_ROUNDNESS,
                ConfigMapper::NAME_SKIN_CATEGORY_DROP_SHADOW,
            ],
            true,
        );
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
