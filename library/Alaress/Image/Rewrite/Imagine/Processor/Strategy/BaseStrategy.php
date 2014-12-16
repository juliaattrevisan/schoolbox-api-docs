<?php
/**
 * Alaress_Image_Processor_Strategy_Abstract
 *
 * Canvas abstract. Used when you need to specify new dimensions for an image.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Imagine\Image\BoxInterface;
use Imagine\Image\Point;
use Imagine\Image\PointInterface;

// Parameters:
// BoxFactory
// PointFactory
// ImagineInterface

abstract class BaseStrategy implements StrategyInterface
{
    /**
     * Unused - was used in old implementation
     *
     * TODO remove once creation logic in Alaress_Image_Processor_Standard->loadProcessorStrategy is in factory
     *
     * @var bool
     */
    protected $supportsAlphaChannel = true;
    protected $canvasWidth = 0;
    protected $canvasHeight = 0;

    /**
     * Translates color array to a hex string, otherwise returns white
     *
     * @param  array  $bgColorArray
     * @return string
     */
    protected function translateColorArray($bgColorArray)
    {
        if (is_array($bgColorArray)) {
            return sprintf(
                '#%02x%02x%02x',
                min(255, max(0, $bgColorArray['red'])),
                min(255, max(0, $bgColorArray['green'])),
                min(255, max(0, $bgColorArray['blue']))
            );
        }
        return '#ffffff';
    }

    /**
     * Gets the top left point of $imageBox when it is aligned at
     * $alignment within $canvasBox.
     *
     * @param BoxInterface $canvasBox
     * @param BoxInterface $imageBox
     * @param string       $alignment
     *
     * @return PointInterface
     */
    protected function getImageBoxPoint(
        BoxInterface $canvasBox,
        BoxInterface $imageBox,
        $alignment = 'center'
    ) {
        $xLeft   = 0;
        $xRight  = $canvasBox->getWidth() - $imageBox->getWidth();
        $xCenter = intval($xRight / 2);
        $yTop    = 0;
        $yBottom = $canvasBox->getHeight() - $imageBox->getHeight();
        $yCenter = intval($yBottom / 2);
        switch (strtolower($alignment)) {
            case 'top':
                $xPoint = $xCenter;
                $yPoint = $yTop;
                break;
            case 'top-right':
                $xPoint = $xRight;
                $yPoint = $yTop;
                break;
            case 'right':
                $xPoint = $xRight;
                $yPoint = $yCenter;
                break;
            case 'bottom-right':
                $xPoint = $xRight;
                $yPoint = $yBottom;
                break;
            case 'bottom':
                $xPoint = $xCenter;
                $yPoint = $yBottom;
                break;
            case 'bottom-left':
                $xPoint = $xLeft;
                $yPoint = $yBottom;
                break;
            case 'left':
                $xPoint = $xLeft;
                $yPoint = $yCenter;
                break;
            case 'top-left':
                $xPoint = $xLeft;
                $yPoint = $yTop;
                break;
            case 'center':
            case 'middle':
            default:
                $xPoint = $xCenter;
                $yPoint = $yCenter;
                break;
        }
        return new Point(intval($xPoint), intval($yPoint));
    }

    /**
    * Set canvas height
    *
    * @param $canvasHeight
    */
    protected function setCanvasHeight($canvasHeight)
    {
        $this->canvasHeight = $canvasHeight;
    }

    /**
    * Get canvas height
    *
    * @return int
    */
    protected function getCanvasHeight()
    {
        return $this->canvasHeight;
    }

    /**
    * Set canvas width
    *
    * @param $canvasWidth
    */
    protected function setCanvasWidth($canvasWidth)
    {
        $this->canvasWidth = $canvasWidth;
    }

    /**
    * Get canvas width
    *
    * @return int
    */
    protected function getCanvasWidth()
    {
        return $this->canvasWidth;
    }

    /**
     * @param  bool  $supportsAlphaChannel
     * @return $this
     */
    public function setSupportsAlphaChannel($supportsAlphaChannel)
    {
        $this->supportsAlphaChannel = $supportsAlphaChannel;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSupportsAlphaChannel()
    {
        return $this->supportsAlphaChannel;
    }
}
