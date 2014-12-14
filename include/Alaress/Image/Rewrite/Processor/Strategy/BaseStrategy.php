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

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Imagine\Image\BoxInterface;
use Imagine\Image\Point;
use Imagine\Image\PointInterface;
use Intervention\Image\Image;

abstract class BaseStrategy implements StrategyInterface
{

    /**
     * Canvas image resource
     *
     * @var Image
     */
    protected $canvas = null;
    protected $canvasWidth = 0;
    protected $canvasHeight = 0;

    /**
     * Does this processor support alpha channel transformation?
     *
     * @var bool
     */
    protected $supports_alpha_channel;

    /**
    * Specify a background colour for canvas if provided
    * otherwise use generic old white
    *
    * @param $bgColorArray
    */
    protected function applyBackgroundColor($bgColorArray)
    {
        $this->getCanvas()->fill($this->translateColorArray($bgColorArray));
    }

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
        switch ($alignment) {
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
            default:
                $xPoint = $xCenter;
                $yPoint = $yCenter;
                break;
        }
        return new Point(intval($xPoint), intval($yPoint));
    }

    /**
    * Figure out what the resultant dimensions of an image are
    * according to a bounding box
    *
    * If it can fit within the canvas, just return original size
    *
    * @param  Image $image
    * @return array
    */
    protected function boundingBox(Image $image)
    {

        /* Figure out resized dimensions
        */
        $currentWidth  = $image->getWidth();
        $currentHeight = $image->getHeight();

        $resizedWidth = $currentWidth;
        $resizedHeight = $currentHeight;

        /* Constrain by width
         */
        if ($currentWidth > $this->getCanvasWidth()) {
            $ratio = $currentWidth / $this->getCanvasWidth();

            $resizedWidth = $currentWidth / $ratio;
            $resizedHeight = $currentHeight / $ratio;
        }

        /* Constrain by height
         */
        if ($resizedHeight > $this->getCanvasHeight()) {
            $ratio = $resizedHeight / $this->getCanvasHeight();

            $resizedWidth = $resizedWidth / $ratio;
            $resizedHeight = $resizedHeight / $ratio;
        }

        return array(
            'width' => $resizedWidth,
            'height' => $resizedHeight,
        );
    }

    /**
    * Set canvas image resource
    *
    * @param Image $canvas
    */
    protected function setCanvas($canvas)
    {
        $this->canvas = $canvas;
    }

    /**
    * Get canvas image resource
    *
    * @return Image
    */
    protected function getCanvas()
    {
        return $this->canvas;
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
     * Fills the given canvas with a blank alpha layer, so that alpha blending
     * looks passable after the image is sharpened.
     *
     * This should be called before calling imagecopyresampled() on $canvas.
     *
     * @param Image $canvas The canvas resource to fill, or
     *                      $this->getCanvas() if not set.
     *
     * @return void
     */
    protected function alphaFill(Image $canvas = null)
    {
        return;
//        if (!($this->getSupportsAlphaChannel())) {
//            return;
//        }
//        if (!isset($canvas)) {
//            $canvas = $this->getCanvas();
//        }
//        imagealphablending($canvas, false);
//        imagesavealpha($canvas, true);
//        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
//        imagefill($canvas, 0, 0, $transparent);
    }

    /**
     * @param  bool  $supports_alpha_channel
     * @return $this
     */
    public function setSupportsAlphaChannel($supports_alpha_channel)
    {
        $this->supports_alpha_channel = $supports_alpha_channel;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSupportsAlphaChannel()
    {
        return $this->supports_alpha_channel;
    }
}
