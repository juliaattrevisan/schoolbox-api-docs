<?php

/**
 * Representation of a color.
 *
 * This now is just a wrapper around a `\SSNepenthe\ColorUtils\Colors\Color`
 * object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Color;

use SSNepenthe\ColorUtils;

class Color implements \Serializable
{
    /**
     * @var ColorUtils\Colors\Color
     */
    private $colorObject;


    /**
     * Constructor
     *
     * @param int   $red
     * @param int   $green
     * @param int   $blue
     * @param float $alpha
     * @throws \InvalidArgumentException @see $this->assertAlphaChannelValue()
     *                                   and $this->assertColorChannelValue()
     */
    public function __construct($red, $green, $blue, $alpha = 1.0)
    {
        $this->colorObject = ColorUtils\rgba($red, $green, $blue, $alpha);
    }


    /**
     * Create a new white color object.
     *
     * @return Color
     */
    public static function white(): Color
    {
        return new Color(0xFF, 0xFF, 0xFF);
    }


    /**
     * Create a new black color object.
     *
     * @return Color
     */
    public static function black(): Color
    {
        return new Color(0x00, 0x00, 0x00);
    }


    /**
     * @param float $alpha
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertAlphaChannelValue()
     */
    public function setAlpha($alpha)
    {
        $this->assertAlphaChannelValue($alpha);
//        $this->alpha = $alpha;
        $this->colorObject = ColorUtils\rgba($this->getRed(), $this->getGreen(), $this->getBlue(), $alpha);
        return $this;
    }


    /**
     * @return float
     */
    public function getAlpha()
    {
        return $this->colorObject->getRgb()->getAlpha();
//        return $this->alpha;
    }


    /**
     * @param int $blue
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertColorChannelValue()
     */
    public function setBlue($blue)
    {
        $this->assertColorChannelValue($blue);
//        $this->blue = intval($blue);
        $this->colorObject = ColorUtils\rgba($this->getRed(), $this->getGreen(), $blue, $this->getAlpha());
        return $this;
    }


    /**
     * @return int
     */
    public function getBlue()
    {
        return $this->colorObject->getRgb()->getBlue();
//        return $this->blue;
    }


    /**
     * @param int $green
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertColorChannelValue()
     */
    public function setGreen($green)
    {
        $this->assertColorChannelValue($green);
//        $this->green = intval($green);
        $this->colorObject = ColorUtils\rgba($this->getRed(), $green, $this->getBlue(), $this->getAlpha());
        return $this;
    }


    /**
     * @return int
     */
    public function getGreen()
    {
        return $this->colorObject->getRgb()->getGreen();
//        return $this->green;
    }


    /**
     * @param int $red
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertColorChannelValue()
     */
    public function setRed($red)
    {
        $this->assertColorChannelValue($red);
//        $this->red = intval($red);
        $this->colorObject = ColorUtils\rgba($red, $this->getGreen(), $this->getBlue(), $this->getAlpha());
        return $this;
    }


    /**
     * @return int
     */
    public function getRed()
    {
        return $this->colorObject->getRgb()->getRed();
//        return $this->red;
    }


    /**
     * Asserts that the value is an integer, and between 0x00 and 0xFF.
     *
     * @param int $value
     * @throws \InvalidArgumentException if color value doesn't match above
     */
    protected function assertColorChannelValue($value)
    {
        if (!preg_match('/^\\d+$/', $value)) {
            throw new \InvalidArgumentException("Color value \"$value\" is not an integer");
        }
        if (intval($value) < 0x00) {
            throw new \InvalidArgumentException("Color value \"$value\" < 0");
        }
        if (intval($value) > 0xFF) {
            throw new \InvalidArgumentException("Color value \"$value\" > 255");
        }
    }


    /**
     * Asserts that the value is an float between 0 and 1.
     *
     * @param float $value
     * @throws \InvalidArgumentException if alpha value doesn't match above
     */
    protected function assertAlphaChannelValue($value)
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Alpha value \"$value\" is not a number");
        }
        if (floatval($value) < 0) {
            throw new \InvalidArgumentException("Alpha value \"$value\" < 0");
        }
        if (floatval($value) > 1) {
            throw new \InvalidArgumentException("Alpha value \"$value\" > 1");
        }
    }


    /**
     * Retrieves the color's HSL values.
     *
     * @return array {hue: float, saturation: float, lightness: float}
     */
    public function getAsHslArray(): array
    {
        return $this->colorObject->getHsl()->toArray();
    }


    /**
     * Outputs as a hex string, no alpha channel.
     *
     * @return string
     */
    public function getAsHex()
    {
        return sprintf(
            '%02X%02X%02X',
            $this->getRed(),
            $this->getGreen(),
            $this->getBlue()
        );
    }


    /**
     * Outputs as a hex string with an alpha channel.
     *
     * @return string
     */
    public function getAsHexAlpha()
    {
        return sprintf(
            '%02X%02X%02X%02X',
            $this->getRed(),
            $this->getGreen(),
            $this->getBlue(),
            intval($this->getAlpha() * 0xFF)
        );
    }


    /**
     * Outputs as a RGB string, no alpha channel.
     *
     * @return string
     */
    public function getAsRgb()
    {
        return sprintf(
            'rgb(%d, %d, %d)',
            $this->getRed(),
            $this->getGreen(),
            $this->getBlue()
        );
    }


    /**
     * Outputs as a RGB string with alpha channel.
     *
     * For now, alpha channel is rounded and padded to 3 decimal places.
     *
     * @return string
     */
    public function getAsRgba()
    {
        return sprintf(
            'rgba(%d, %d, %d, %0.3f)',
            $this->getRed(),
            $this->getGreen(),
            $this->getBlue(),
            $this->getAlpha()
        );
    }


    /**
     * Outputs this color in RGBA format.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getAsRgba();
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize([
            'r' => $this->getRed(),
            'g' => $this->getGreen(),
            'b' => $this->getBlue(),
            'a' => $this->getAlpha(),
        ]);
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     *
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     *                           The string representation of the object.
     *                           </p>
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->colorObject = ColorUtils\rgba($data['r'], $data['g'], $data['b'], $data['a']);
    }
}
