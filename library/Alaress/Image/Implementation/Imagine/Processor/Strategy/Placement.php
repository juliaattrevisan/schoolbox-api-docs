<?php
/**
 * Alaress_Image_Processor_Strategy_Placement
 *
 * Place an image on a canvas at one of the nine cardinal locations. Defaults to 'topleft'
 *
 * topleft, top, topright
 * left, middle, right
 * bottomleft, bottom, bottomright
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class Placement extends BaseStrategy
{

    /* Positioning of the image within the canvas
     */
    private $position = 'topleft';

    /**
    * Background colours are used for deciding transparency in pngs
    *
    * @var array
    */
    private $bgColors = array();

    /**
     * {@inheritdoc}
     */
    protected function setup($args)
    {
        $this->setCanvasWidth($args['width']);
        $this->setCanvasHeight($args['height']);
        $this->setPosition($args['position']);
        $this->setBgColors($args['bgcolor']);
    }

    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        $canvasBox = new Box(
            $this->getCanvasWidth(),
            $this->getCanvasHeight()
        );

        // If canvas is smaller than image, then crop image to canvas
        if (!$canvasBox->contains($image->getSize())) {
            $cropBox = new Box(
                min($canvasBox->getWidth(), $image->getSize()->getWidth()),
                min($canvasBox->getHeight(), $image->getSize()->getHeight())
            );
            $image->crop(
                $this->getImageBoxPoint($image->getSize(), $cropBox, $this->getPosition()),
                $cropBox
            );
        }
        return $image;
    }

    protected function translatePositioning()
    {
        $position = $this->getPosition();
        switch ($position) {
            case 'topleft':
                return 'top-left';
            case 'topright':
                return 'top-right';
            case 'bottomright':
                return 'bottom-right';
            case 'bottomleft':
                return 'bottom-left';
            case 'top':
            case 'right':
            case 'bottom':
            case 'left':
            case 'center':
            default:
                return $position;
        }
    }

    /* Getters/Setters
     */

    /**
    * Set position
    *
    * @param $position
    */
    private function setPosition($position)
    {
        $this->position = $position;
    }

    /**
    * Get position
    *
    * @return string
    */
    private function getPosition()
    {
        return $this->position;
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
