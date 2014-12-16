<?php
/**
 * Alaress_Image_Processor_Strategy_Rectangle
 *
 * Constrain an image to fit within a specified width and height which is measured
 * from the center of the source image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class Rectangle extends BaseStrategy
{
    /**
     * Images smaller than the canvas maintain their dimensions and don't
     * get resized if the canvas is larger.
     *
     * Set to true if you want it to upscale.
     *
     * @var bool
     */
    protected $smallImageUpscales = true;

    /**
     * A placement processor
     *
     * @var BaseStrategy
     */
    protected $placementProcessor;

    /**
     * {@inheritdoc}
     */
    protected function setup($args)
    {
        $this->setCanvasWidth($args['width']);
        $this->setCanvasHeight($args['height']);
        $this->setSmallImageUpscales($args['smallImageUpscales']);
        $this->placementProcessor = new Placement($args, $this->getImageManager());
    }

    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        // This processor:
        // resize:
        // * scale down so one dimension fits, if image doesn't fit canvas
        // * scale up to max size that fits within canvas, if it does
        //   (and smallImageUpscales is on)
        // placement:
        // * place resized image in center of canvas
        $currentWidth  = $image->getSize()->getWidth();
        $currentHeight = $image->getSize()->getHeight();

        $currentRatio = $currentWidth / $currentHeight;
        $finalRatio   = $this->getCanvasWidth() / $this->getCanvasHeight();

        $canvasBox = new Box(
            $this->getCanvasWidth(),
            $this->getCanvasHeight()
        );

        if (!$canvasBox->contains($image->getSize())) {
            // Image doesn't fit within rectangle.
            // Resize it down so one dimension does.
            if ($currentRatio > $finalRatio) {
                $image->resize($image->getSize()->heighten($this->getCanvasHeight()));
            } else {
                $image->resize($image->getSize()->widen($this->getCanvasWidth()));
            }
        } elseif ($this->getSmallImageUpscales()) {
            // Image does fit, but it's getting scaled up.
            // Scale it up so that it barely fits.
            if ($currentRatio > $finalRatio) {
                $image->resize($image->getSize()->widen($this->getCanvasWidth()));
            } else {
                $image->resize($image->getSize()->heighten($this->getCanvasHeight()));
            }
        }

        // And send this to the placement processor
        $image = $this->placementProcessor->manipulate($image);
        return $image;
    }

    /* Getters/Setters
     */

    /**
     * Set smallImageUpscales
     * @param boolean $smallImageUpscales
     */
    public function setSmallImageUpscales($smallImageUpscales)
    {
        $this->smallImageUpscales = $smallImageUpscales;
    }

    /**
     * Get smallImageUpscales
     *
     * @return boolean
     */
    public function getSmallImageUpscales()
    {
        return $this->smallImageUpscales;
    }
}
