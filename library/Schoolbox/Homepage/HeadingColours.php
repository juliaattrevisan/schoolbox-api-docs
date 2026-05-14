<?php

declare(strict_types=1);

namespace Schoolbox\Homepage;

use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;

class HeadingColours
{
    private const POSITIONS = ['left', 'right', 'top'];

    public function __construct(private ?IConfigLoader $configLoader = null)
    {
    }

    /**
     * Gets the effective default heading colours for homepage-style layouts.
     *
     * @return array{head_font: mixed, head_bg: mixed}
     */
    public function getDefault(): array
    {
        if ($this->isAdvancedUiEnabled()) {
            return [
                'head_font' => $this->getConfig('skin_colour_component_heading_text'),
                'head_bg'   => $this->getConfig('skin_colour_component_heading_background'),
            ];
        }

        return $this->getStoredDefault();
    }

    /**
     * Gets the legacy stored default heading colours for homepage-style layouts.
     *
     * @return array{head_font: mixed, head_bg: mixed}
     */
    public function getStoredDefault(): array
    {
        return [
            'head_font' => $this->getConfig('homepage_colour_head_font'),
            'head_bg'   => $this->getConfig('homepage_colour_head_bg'),
        ];
    }

    public function normaliseForDisplay(array $colourList): array
    {
        if (!$this->isAdvancedUiEnabled()) {
            return $colourList;
        }

        return $this->normaliseHeadingColours(
            $colourList,
            $this->getStoredDefault(),
            $this->getDefault(),
        );
    }

    public function normaliseForSave(array $colourList): array
    {
        if (!$this->isAdvancedUiEnabled()) {
            return $colourList;
        }

        return $this->normaliseHeadingColours(
            $colourList,
            $this->getDefault(),
            $this->getStoredDefault(),
        );
    }

    /**
     * @param array<string, mixed> $colourList
     * @param array{head_font: mixed, head_bg: mixed} $matchingColours
     * @param array{head_font: mixed, head_bg: mixed} $replacementColours
     * @return array<string, mixed>
     */
    private function normaliseHeadingColours(
        array $colourList,
        array $matchingColours,
        array $replacementColours
    ): array {
        foreach (self::POSITIONS as $position) {
            if ($this->hasHeadingColours($colourList, $position, $matchingColours)) {
                $colourList[$position . '_head_font'] = $replacementColours['head_font'];
                $colourList[$position . '_head_bg']   = $replacementColours['head_bg'];
            }
        }

        return $colourList;
    }

    /**
     * @param array<string, mixed> $colourList
     * @param array{head_font: mixed, head_bg: mixed} $headingColours
     */
    private function hasHeadingColours(array $colourList, string $position, array $headingColours): bool
    {
        $headFont = $colourList[$position . '_head_font'] ?? '';
        $headBg   = $colourList[$position . '_head_bg'] ?? '';

        return strcasecmp((string)$headFont, (string)$headingColours['head_font']) === 0
            && strcasecmp((string)$headBg, (string)$headingColours['head_bg']) === 0;
    }

    private function isAdvancedUiEnabled(): bool
    {
        return (bool)$this->getConfig('advanced_ui_enabled');
    }

    private function getConfig(string $configName): mixed
    {
        return $this->getConfigLoader()->get($configName);
    }

    private function getConfigLoader(): IConfigLoader
    {
        if (!$this->configLoader) {
            $this->configLoader = Loader::getInstance();
        }

        return $this->configLoader;
    }
}
