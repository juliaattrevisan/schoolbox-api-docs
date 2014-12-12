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

use Intervention\Image\Image;
use Intervention\Image\ImageManager;

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

        /* Create canvas at specified dimensions
         */
        $manager = new ImageManager();
        $this->setCanvas($manager->canvas($this->getCanvasWidth(), $this->getCanvasHeight()));

        /* Apply background color
         */
        $this->setBgColors($args['bgcolor']);
        $this->applyBackgroundColor($args['bgcolor']);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(Image $image)
    {
        return $image->resize($image->getWidth(), $image->getHeight());
//
//        /* Figure out resized dimensions
//        */
//        $dimensions = $this->boundingBox($image);
//
//        $bgColor = $this->getBgColors();
//
//        $this->alphaFill();
//        imagecopyresampled($this->getCanvas(), $image, 0, 0, 0, 0, $dimensions['width'], $dimensions['height'], imagesx($image), imagesy($image));
//
//        return $this->getCanvas();
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
