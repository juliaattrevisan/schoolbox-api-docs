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

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;

class Rectangle extends BaseStrategy implements StrategyInterface
{

    /**
    * Images smaller than the canvas maintain their dimensions and don't
    * get resized if the canvas is larger.
    *
    * Set to true if you want it to upscale.
    *
    * @var bool
    */
    private $smallImageUpscales = true;

    /**
    * Background colours are used for deciding transparency in pngs
    *
    * @var array
    */
    private $bgColors = array();

    /**
    * Constructor
    *
    * @param $args
    */
    public function __construct($args)
    {
        $this->setCanvasWidth($args['width']);
        $this->setCanvasHeight($args['height']);

//        /* Create canvas at specified dimensions
//         */
//        $manager = new ImageManager();
//        $this->setCanvas($manager->canvas($this->getCanvasWidth(), $this->getCanvasHeight()));

        /* Apply background color
         */
        $this->setBgColors($args['bgcolor']);
//        $this->applyBackgroundColor($args['bgcolor']);

        $this->setSmallImageUpscales($args['smallImageUpscales']);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(Image $image)
    {
        // Scale to largest of width/height,
        // then crop to centered rectangle
        $currentWidth  = $image->getWidth();
        $currentHeight = $image->getHeight();

        $currentRatio = $currentWidth / $currentHeight;
        $finalRatio   = $this->getCanvasWidth() / $this->getCanvasHeight();

        if ($currentWidth > $this->getCanvasWidth() || $currentHeight > $this->getCanvasHeight()) {
            // Image doesn't fit within rectangle.
            // Resize it down so one dimension does.
            if ($currentRatio > $finalRatio) {
                $image->heighten($this->getCanvasHeight());
            } else {
                $image->widen($this->getCanvasWidth());
            }
        } elseif ($this->getSmallImageUpscales()) {
            // Image does fit, but it's getting scaled up.
            // Scale it up so that it barely fits.
            if ($currentRatio > $finalRatio) {
                $image->widen($this->getCanvasWidth());
            } else {
                $image->heighten($this->getCanvasHeight());
            }
        }

        return $image->resizeCanvas(
            $this->getCanvasWidth(),
            $this->getCanvasHeight(),
            'center',
            false,
            $this->translateColorArray($this->getBgColors())
        );
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

    /**
    * Set bgcolours
    *
    * @param array $bgColors
    */
    private function setBgColors($bgColors)
    {
        $this->bgColors = $bgColors;
    }

    /**
    * Get bgcolours
    *
    * @return array
    */
    private function getBgColors()
    {
        return $this->bgColors;
    }
}
