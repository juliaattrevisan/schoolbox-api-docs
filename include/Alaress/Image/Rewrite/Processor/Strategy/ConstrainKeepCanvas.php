<?php
/**
 * Alaress_Image_Processor_Strategy_ConstrainKeepCanvas
 *
 * Constrain an image to fit within a specified width and height
 *
 * The entire original image will be resized with aspect preserved, to fill the maximum amount
 * of space possible without having any of the original image cropped out.
 *
 * If your canvas is larger than the resized image, the excess canvas space is kept
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Imagine\Image\Fill;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

class ConstrainKeepCanvas extends BaseStrategy implements StrategyInterface
{

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
        $this->setBgColors($args['bgcolor']);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
//        new \Imagine\Filter\Advanced\Canvas()
        $canvas  = clone $image;
        $palette = $canvas->palette();
        $color   = $palette->color($this->translateColorArray($this->getBgColors()));
        $canvas->fill(
            new Fill\Gradient\Horizontal(1, $color, $color)
        );
        $image->thumbnail(
            new Box(
                $this->getCanvasWidth(),
                $this->getCanvasHeight()
            )
        );
        $canvas->paste($image, new Point(0, 0));
        return $canvas;
    }

    /* Getters/Setters
     */

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
