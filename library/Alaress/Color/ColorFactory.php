<?php

/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Color;

class ColorFactory
{
    /**
     * Creates a color from a color string.
     *
     * @param string $colorString A string in one of these formats:
     *                            * RRGGBB (optional leading #)
     *                            * RRGGBBAA (optional leading #)
     *                            * rgb(int, int, int)
     *                            * rgba(int, int, int, float)
     * @return Color
     * @throws \InvalidArgumentException
     */
    public function build($colorString): Color
    {
        // Formats:
        // RRGGBB
        // RRGGBBAA
        // rgb(r,g,b)
        // rgba(r,g,b,a)
        // Remove all spaces for comparison
        //TODO to investigate why the $colorString was null, and force string on input
        $colorString = preg_replace('/\\s/', '', (string)$colorString);
        if (
            preg_match(
                '/^#?(?P<r>[0-9a-f]{2})(?P<g>[0-9a-f]{2})(?P<b>[0-9a-f]{2})$/i',
                $colorString,
                $matches
            )
        ) {
            return new Color(
                hexdec($matches['r']),
                hexdec($matches['g']),
                hexdec($matches['b'])
            );
        }
        if (
            preg_match(
                '/^#?(?P<r>[0-9a-f]{2})(?P<g>[0-9a-f]{2})(?P<b>[0-9a-f]{2})(?P<a>[0-9a-f]{2})$/i',
                $colorString,
                $matches
            )
        ) {
            return new Color(
                hexdec($matches['r']),
                hexdec($matches['g']),
                hexdec($matches['b']),
                (hexdec($matches['a']) / 0xFF)
            );
        }
        if (
            preg_match(
                '/^rgb\\((?P<r>\\d+),(?P<g>\\d+),(?P<b>\\d+)\\)$/i',
                $colorString,
                $matches
            )
        ) {
            return new Color(
                $matches['r'],
                $matches['g'],
                $matches['b']
            );
        }
        if (
            preg_match(
                '/^rgba\\((?P<r>\\d+),(?P<g>\\d+),(?P<b>\\d+),(?P<a>[\\d\\.]+)\\)$/i',
                $colorString,
                $matches
            )
        ) {
            // Test that alpha channel is a number between 0 and 1 here
            if (is_numeric($matches['a'])) {
                return new Color(
                    $matches['r'],
                    $matches['g'],
                    $matches['b'],
                    $matches['a']
                );
            }
        }
        throw new \InvalidArgumentException("Value \"$colorString\" does not match expected format");
    }
}
