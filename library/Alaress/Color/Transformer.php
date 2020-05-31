<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Color;

use SSNepenthe\ColorUtils;

/**
 * Functions for transforming colors.
 */
class Transformer
{
    /**
     * Port of Schoolbox-written SASS function contrast-bg.
     *
     * This function appears to build a color that is guaranteed to contrast
     * with a background, with some parameters given for tweaking.
     *
     * @param Color      $background The background color to contrast with
     * @param float      $opacity
     * @param float      $saturation
     * @param Color|null $light
     * @param Color|null $dark
     * @return Color
     */
    public function buildContrastingColor(
        Color $background,
        float $opacity = .35,
        float $saturation = 0.1,
        Color $light = null,
        Color $dark = null
    ): Color {
        $colorUtilsBackground = $this->convertToColorUtilsColor($background);
        $luminance            = ColorUtils\relative_luminance($colorUtilsBackground);
        if ($luminance > 0.5) {
            $textColor = $light
                ? $this->convertToColorUtilsColor($light)
                : ColorUtils\color('white');
        } else {
            $textColor = $dark
                ? $this->convertToColorUtilsColor($dark)
                : ColorUtils\color('black');
        }

//        $translucency = ColorUtils\color($textColor->getHsl()->toArray() + ['alpha' => $opacity]);

        // flatten opacity onto the background
        // $flat: flatten-alpha($translucent, $background);
//        $percent = $opacity;
        $opaque = ColorUtils\color($textColor->getHsl()->toArray() + ['alpha' => 1]);

        // $solid-color: mix($opaque, $background, $percent);
        $flat = ColorUtils\mix($opaque, $colorUtilsBackground, $opacity);

//        // boost saturation
//        $saturated: saturate($flat, $saturation);

        $saturated = ColorUtils\saturate($flat, $saturation);
        return $this->convertToAlaressColor($saturated);
//        return new Color(
//            ColorUtils\red($saturated),
//            ColorUtils\green($saturated),
//            ColorUtils\blue($saturated),
//            ColorUtils\alpha($saturated)
//        );
    }


    private function convertToColorUtilsColor(Color $color): ColorUtils\Colors\Color
    {
        return ColorUtils\rgba(
            $color->getRed(),
            $color->getGreen(),
            $color->getBlue(),
            $color->getAlpha()
        );
    }


    private function convertToAlaressColor(ColorUtils\Colors\Color $color): Color
    {
        return new Color(
            ColorUtils\red($color),
            ColorUtils\green($color),
            ColorUtils\blue($color),
            ColorUtils\alpha($color)
        );
    }
}
