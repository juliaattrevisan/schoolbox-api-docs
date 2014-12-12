<?php
/**
 * Alaress_Image_Processor_Strategy_Constrain
 *
 * Constrain an image to fit within a specified width and height
 *
 * The entire original image will be resized with aspect preserved, to fill the maximum amount
 * of space possible without having any of the original image cropped out.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;

class Constrain extends BaseStrategy implements StrategyInterface
{
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
//        /* If resultant dimensions are the same, just return
//         * what you were provided instead of copying out again
//         */
//        if (($dimensions['width'] == imagesx($image)) && ($dimensions['height'] == imagesy($image))) {
//            return $image;
//        } /* Resize the canvas to fit the new resized dimensions, so
//         * you don't get excess canvas space
//         */
//        else {
//            $resizedCanvas = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
//            $this->alphaFill($resizedCanvas);
//            imagecopyresampled($resizedCanvas, $image, 0, 0, 0, 0, $dimensions['width'], $dimensions['height'], imagesx($image), imagesy($image));
//
//            return $resizedCanvas;
//        }
    }
}
