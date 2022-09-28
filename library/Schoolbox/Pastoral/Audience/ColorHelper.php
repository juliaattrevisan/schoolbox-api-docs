<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress\Color\Transformer;

/**
 * Helper for determining the background color of an audience flag, given a
 * foreground color.
 */
class ColorHelper
{
    public const BASE_BACKGROUND_COLOR_DARK  = '#333333';
    public const BASE_BACKGROUND_COLOR_LIGHT = '#DDDDDD';

    private Transformer $transformer;

    private ColorFactory $colorFactory;

    /**
     * Calculates the background color for a flag, given its foreground.
     *
     * @param Color|null $color The foreground color, defaults to black if not
     *                          set.
     * @return Color
     */
    public function calculateBackground(?Color $color): Color
    {
        $baseDark  = $this->getColorFactory()->build(self::BASE_BACKGROUND_COLOR_DARK);
        $baseLight = $this->getColorFactory()->build(self::BASE_BACKGROUND_COLOR_LIGHT);
        return $this->getTransformer()->calculateShadedBackgroundColor($color, $baseLight, $baseDark);
    }

    /**
     * Calculates the foreground color, given its background.
     *
     * @param Color $color The background color
     * @return Color
     */
    public function calculateForeground(Color $color): Color
    {
        $baseDark  = $this->getColorFactory()->build(self::BASE_BACKGROUND_COLOR_DARK);
        $baseLight = $this->getColorFactory()->build(self::BASE_BACKGROUND_COLOR_LIGHT);
        return $this->getTransformer()->calculateForegroundColor($color, 1, 0.5, $baseLight, $baseDark);
    }

    /**
     * @return Transformer
     */
    private function getTransformer(): Transformer
    {
        if (!isset($this->transformer)) {
            $this->transformer = new Transformer();
        }
        return $this->transformer;
    }

    /**
     * @return ColorFactory
     */
    public function getColorFactory(): ColorFactory
    {
        if (!isset($this->colorFactory)) {
            $this->colorFactory = new ColorFactory();
        }
        return $this->colorFactory;
    }
}
