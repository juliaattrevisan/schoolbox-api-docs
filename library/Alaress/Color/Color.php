<?php

declare(strict_types = 1);

/**
 * Representation of a color.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.education
 */

namespace Alaress\Color;

class Color
{
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
    public function __construct(
        private int $red,
        private int $green,
        private int $blue,
        private float $alpha = 1.0,
    ) {
        $this->assertColorChannelValue($red);
        $this->assertColorChannelValue($green);
        $this->assertColorChannelValue($blue);
        $this->assertAlphaChannelValue($alpha);
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
    public function setAlpha(float $alpha): self
    {
        $this->assertAlphaChannelValue($alpha);
        $this->alpha = $alpha;
        return $this;
    }


    /**
     * @return float
     */
    public function getAlpha(): float
    {
        return $this->alpha;
    }


    /**
     * @param int $blue
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertColorChannelValue()
     */
    public function setBlue(int $blue): self
    {
        $this->assertColorChannelValue($blue);
        $this->blue = $blue;
        return $this;
    }


    /**
     * @return int
     */
    public function getBlue(): int
    {
        return $this->blue;
    }


    /**
     * @param int $green
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertColorChannelValue()
     */
    public function setGreen(int $green): self
    {
        $this->assertColorChannelValue($green);
        $this->green = $green;
        return $this;
    }


    /**
     * @return int
     */
    public function getGreen(): int
    {
        return $this->green;
    }


    /**
     * @param int $red
     * @return $this
     * @throws \InvalidArgumentException @see $this->assertColorChannelValue()
     */
    public function setRed(int $red): self
    {
        $this->assertColorChannelValue($red);
        $this->red = $red;
        return $this;
    }


    /**
     * @return int
     */
    public function getRed(): int
    {
        return $this->red;
    }


    /**
     * Asserts that the value is between 0x00 and 0xFF.
     *
     * @param int $value
     * @throws \InvalidArgumentException if color value doesn't match above
     */
    protected function assertColorChannelValue(int $value): void
    {
        if ($value < 0x00) {
            throw new \InvalidArgumentException("Color value \"$value\" < 0");
        }
        if ($value > 0xFF) {
            throw new \InvalidArgumentException("Color value \"$value\" > 255");
        }
    }


    /**
     * Asserts that the value is between 0 and 1.
     *
     * @param float $value
     * @throws \InvalidArgumentException if alpha value doesn't match above
     */
    protected function assertAlphaChannelValue(float $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("Alpha value \"$value\" < 0");
        }
        if ($value > 1) {
            throw new \InvalidArgumentException("Alpha value \"$value\" > 1");
        }
    }


    /**
     * Outputs as a hex string, no alpha channel.
     *
     * @return string
     */
    public function getAsHex(): string
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
    public function getAsHexAlpha(): string
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
    public function getAsRgb(): string
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
    public function getAsRgba(): string
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
    public function __toString(): string
    {
        return $this->getAsRgba();
    }


    /**
     * Array representation of object
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'r' => $this->red,
            'g' => $this->green,
            'b' => $this->blue,
            'a' => $this->alpha,
        ];
    }


    /**
     * Constructs the object
     *
     * @param array $serialized
     *
     * @return void
     */
    public function __unserialize(array $serialized): void
    {
        if (isset($serialized['r'])) {
            $this->red = $serialized['r'];
        }
        if (isset($serialized['g'])) {
            $this->green = $serialized['g'];
        }
        if (isset($serialized['b'])) {
            $this->blue = $serialized['b'];
        }
        if (isset($serialized['a'])) {
            $this->alpha = $serialized['a'];
        }
    }
}
