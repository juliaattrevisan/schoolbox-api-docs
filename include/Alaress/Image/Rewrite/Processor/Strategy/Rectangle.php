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

        /* Create canvas at specified dimensions
         */
        $manager = new ImageManager();
        $this->setCanvas($manager->canvas($this->getCanvasWidth(), $this->getCanvasHeight()));

        /* Apply background color
         */
        $this->setBgColors($args['bgcolor']);
        $this->applyBackgroundColor($args['bgcolor']);

        $this->setSmallImageUpscales($args['smallImageUpscales']);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(Image $image)
    {

        /* Center of source image
         */
        $currentWidth  = $image->getWidth();
        $currentHeight = $image->getHeight();

        $currentRatio = $currentWidth / $currentHeight;
        $finalRatio = $this->getCanvasWidth() / $this->getCanvasHeight();

        /* Resize if image is larger than the canvas in either dimension
         * (or upscaling small images)
         */
        if ($currentWidth > $this->getCanvasWidth()
            || $currentHeight > $this->getCanvasHeight()
            || $this->getSmallImageUpscales()
        ) {
            if (($this->getCanvasWidth()/$this->getCanvasHeight()) >= 1) {
                /* Landscape
                 */
                if ($currentRatio > $finalRatio) {
                    /* Current image is more panoramic than final canvas -_-;
                     */
                    /* The edges that get lopped off
                     */
                    $trim = abs(0.5 * ($currentHeight * ($finalRatio - $currentRatio)));

                    $startX = $trim;
                    $startY = 0;

                    $endX = $currentWidth - 2*$trim;
                    $endY = $currentHeight;
                } else {
                    /* Canvas is more panoramic than current image -_-;
                     */
                    /* The edges that get lopped off
                     */
                    $trim = abs(0.5 * ($currentWidth/$finalRatio - $currentWidth/$currentRatio));

                    $startX = 0;
                    $startY = $trim;

                    $endX = $currentWidth;
                    $endY = $currentHeight - 2*$trim;
                }
            } else {
                /* Portrait
                 */
                if ($currentRatio > $finalRatio) {
                    /* Canvas is taller than current image -_-;
                     */
                    /* The edges that get lopped off
                     */
                    $trim = abs(0.5 * ($currentHeight * ($finalRatio - $currentRatio)));

                    $startX = $trim;
                    $startY = 0;

                    $endX = $currentWidth - 2*$trim;
                    $endY = $currentHeight;
                } else {
                    /* Current image is taller than final canvas -_-;
                     */
                    /* The edges that get lopped off
                     */
                    $trim = abs(0.5 * ($currentWidth/$finalRatio - $currentWidth/$currentRatio));

                    $startX = 0;
                    $startY = $trim;

                    $endX = $currentWidth;
                    $endY = $currentHeight - 2*$trim;
                }
            }

            /* Resize the canvas to fit the new resized dimensions, so
             * you don't get excess canvas space
             */
            return $image
                ->resize($endX, $endY)
                ->resizeCanvas($this->getCanvasWidth(), $this->getCanvasHeight(), 'center')
            ;
//            $this->alphaFill();
//            imagecopyresampled(
//                $this->getCanvas(),
//                $image,
//                0,
//                0,
//                $startX,
//                $startY,
//                $this->getCanvasWidth(),
//                $this->getCanvasHeight(),
//                $endX,
//                $endY
//            );
        } else {
            return $image->resizeCanvas($currentWidth, $currentHeight, 'center');
//            // Create a new truecolor canvas of the same size, and copy onto it.
//            $this->setCanvas(imagecreatetruecolor($currentWidth, $currentHeight));
//            $this->alphaFill();
//            imagecopy($this->getCanvas(), $image, 0, 0, 0, 0, $currentWidth, $currentHeight);
        }
//
//
//        return $this->getCanvas();
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
