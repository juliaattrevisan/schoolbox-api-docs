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
     * Port of Schoolbox-written SASS function `calc-text-color`.
     *
     * @param Color      $color     The color to contrast with
     * @param Color|null $light     If `$color` is dark, return this, or white
     *                              if not defined
     * @param Color|null $dark      If `$color` is light, return this, or black
     *                              if not defined
     * @param float      $threshold If relative luminance of `$color` is less
     *                              than this, it is dark; otherwise light
     * @return Color
     */
    public function calculateTextColor(
        Color $color,
        Color $light = null,
        Color $dark = null,
        float $threshold = 0.45
    ): Color {
        $luminance = ColorUtils\relative_luminance($this->convertToColorUtilsColor($color));
        return $luminance < $threshold
            ? ($light ?? Color::white())
            : ($dark ?? Color::black());
    }


    /**
     * Port of Schoolbox-written SASS function `contrast-bg`.
     *
     * This function appears to build a color that is guaranteed to contrast
     * with a background, with some parameters given for tweaking.
     *
     * @param Color      $background The background color to contrast with.
     * @param float      $opacity    What proportion does the contrasting color
     *                               show through the background?
     * @param float      $saturation How saturated is the new contrasting color?
     * @param Color|null $light      If `$background` is dark, use this color as
     *                               the base for the foreground (to which
     *                               `$opacity` and `$saturation` are applied).
     *                               Defaults to white.
     * @param Color|null $dark       If `$background` is light, use this color
     *                               as the base for the foreground (to which
     *                               `$opacity` and `$saturation` are applied).
     *                               Defaults to black.
     * @return Color
     */
    public function calculateForegroundColor(
        Color $background,
        float $opacity = .35,
        float $saturation = 0.1,
        Color $light = null,
        Color $dark = null
    ): Color {
        // Is $background light or dark? Choose a contrasting color for text
        $textColor = $this->calculateTextColor($background, $light, $dark, 0.5);

        // apply the opacity given
        $translucent = $textColor->setAlpha($opacity);

        // flatten opacity onto the background
        // This uses another SASS function flatten-alpha
        $flat = $this->convertToColorUtilsColor($this->flattenAlpha($translucent, $background));

        // boost saturation
        $saturated = ColorUtils\saturate($flat, $saturation);
        return $this->convertToAlaressColor($saturated);
    }


    /**
     * Port of Schoolbox-written SASS function `flatten-alpha`.
     *
     * Flattens partially transparent `$color` onto `$background`.
     *
     * @param Color $color
     * @param Color $background
     * @return Color
     */
    public function flattenAlpha(Color $color, Color $background): Color
    {
        $percent = $color->getAlpha() * 100;
        $opaque  = ColorUtils\color(['alpha' => 1] + $this->convertToColorUtilsColor($color)->getHsl()->toArray());
        return $this->convertToAlaressColor(
            ColorUtils\mix(
                $opaque,
                $this->convertToColorUtilsColor($background),
                (int)$percent
            )
        );
    }


    /**
     * Calculate a contrasting background with the same shade (hue+saturation)
     * as the foreground.
     *
     * @param Color|null $foreground The foreground color, defaults to black if
     *                               not set.
     * @param Color|null $light      The color to use as the base for the
     *                               background, if `$foreground` is light.
     * @param Color|null $dark       The color to use as the base for the
     *                               background, if `$foreground` is dark.
     * @return Color
     */
    public function calculateShadedBackgroundColor(
        Color $foreground = null,
        Color $light = null,
        Color $dark = null
    ): Color {
        $foregroundColor     = $this->convertToColorUtilsColor($foreground);
        $foregroundHsl       = $foregroundColor->getHsl();
        $basebackgroundColor = $this->convertToColorUtilsColor(
            $this->calculateTextColor($foreground ?? Color::black(), $light, $dark)
        );
        $basebackgroundHsl   = $basebackgroundColor->getHsl();

        return $this->convertToAlaressColor(
            ColorUtils\hsla(
                $foregroundHsl->getHue(),
                $foregroundHsl->getSaturation(),
                $basebackgroundHsl->getLightness(),
                $foregroundHsl->getAlpha()
            )
        );
    }


    /**
     * Convert an Alaress color object to a ColorUtils color object.
     *
     * @param Color $color
     * @return ColorUtils\Colors\Color
     */
    private function convertToColorUtilsColor(Color $color): ColorUtils\Colors\Color
    {
        return ColorUtils\rgba(
            $color->getRed(),
            $color->getGreen(),
            $color->getBlue(),
            $color->getAlpha()
        );
    }


    /**
     * Convert a ColorUtils color object to an Alaress color object.
     *
     * @param ColorUtils\Colors\Color $color
     * @return Color
     */
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
