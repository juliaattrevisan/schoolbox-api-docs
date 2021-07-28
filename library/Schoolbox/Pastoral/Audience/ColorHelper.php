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
    public const BASE_BACKGROUND_COLOR_DARK = '#333333';
    public const BASE_BACKGROUND_COLOR_LIGHT = '#DDDDDD';

    /**
     * Calculates the background color for a flag, given its foreground.
     *
     * @param Color|null $color The foreground color, defaults to black if not
     *                          set.
     * @return Color
     */
    public function calculateBackground(?Color $color): Color
    {
        $transformer    = new Transformer();
        $colorFactory   = new ColorFactory();
        $baseDark       = $colorFactory->build(self::BASE_BACKGROUND_COLOR_DARK);
        $baseLight      = $colorFactory->build(self::BASE_BACKGROUND_COLOR_LIGHT);
        return $transformer->calculateShadedBackgroundColor($color, $baseLight, $baseDark);
    }
}
