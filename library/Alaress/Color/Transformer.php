<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Color;

use OzdemirBurak\Iris\BaseColor;
use OzdemirBurak\Iris\Color\Hexa;

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
        ?Color $light = null,
        ?Color $dark = null,
        float $threshold = 0.45
    ): Color {
        $luminance = $this->calculateRelativeLuminance($this->convertToIrisColor($color));
        return $luminance < $threshold
            ? ($light ?? Color::white())
            : ($dark ?? Color::black());
    }

    /**
     * Calculate relative luminance of a color, based on the definition in
     * https://www.w3.org/TR/2008/REC-WCAG20-20081211/#relativeluminancedef.
     *
     * @param BaseColor $color
     * @return float
     */
    private function calculateRelativeLuminance(BaseColor $color): float
    {
        return
            0.2126 * $this->calculateChannelLuminance($color->toRgb()->red())
            + 0.7152 * $this->calculateChannelLuminance($color->toRgb()->green())
            + 0.0722 * $this->calculateChannelLuminance($color->toRgb()->blue())
        ;
    }

    /**
     * Calculate the luminance of a color channel value.
     *
     * @param int $value
     * @return float
     */
    private function calculateChannelLuminance(int $value): float
    {
        $ratio = $value / 255;
        if ($ratio <= 0.03928) {
            return $ratio / 12.92;
        }
        return (($ratio + 0.055) / 1.055) ** 2.4;
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
        ?Color $light = null,
        ?Color $dark = null
    ): Color {
        // Is $background light or dark? Choose a contrasting color for text
        $textColor = $this->calculateTextColor($background, $light, $dark, 0.5);

        // apply the opacity given
        $translucent = $textColor->setAlpha($opacity);

        // flatten opacity onto the background
        // This uses another SASS function flatten-alpha
        $flat = $this->convertToIrisColor($this->flattenAlpha($translucent, $background));

        // boost saturation
        $saturated = $flat->saturate($saturation);
        return $this->convertFromIrisColor($saturated);
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
        $opaque  = $this->convertToIrisColor($color)->toHsla()->alpha($percent);
        return $this->convertFromIrisColor(
            $opaque->mix($this->convertToIrisColor($background), $percent),
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
        ?Color $foreground = null,
        ?Color $light = null,
        ?Color $dark = null
    ): Color {
        $foregroundColor     = $this->convertToIrisColor($foreground)->toHsla();
        $basebackgroundColor = $this->convertToIrisColor(
            $this->calculateTextColor($foreground ?? Color::black(), $light, $dark)
        )->toHsla();

        return $this->convertFromIrisColor(
            (clone $foregroundColor)->lightness($basebackgroundColor->lightness()),
        );
    }


    /**
     * Convert an Alaress color object to an Iris color object.
     *
     * @param Color $color
     * @return BaseColor
     */
    private function convertToIrisColor(Color $color): BaseColor
    {
        return new Hexa($color->getAsHexAlpha());
    }


    /**
     * Convert an Iris color object to an Alaress color object.
     *
     * @param BaseColor $color
     * @return Color
     */
    private function convertFromIrisColor(BaseColor $color): Color
    {
        $newColor = (clone $color)->toRgba();
        return new Color($newColor->red(), $newColor->green(), $newColor->blue(), $newColor->alpha());
    }
}
