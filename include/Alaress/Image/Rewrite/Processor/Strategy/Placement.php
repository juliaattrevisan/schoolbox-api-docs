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

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class Placement extends BaseStrategy implements StrategyInterface
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
    * Constructor
    *
    * @param $args
    */
    public function __construct($args)
    {
        $this->setCanvasWidth($args['width']);
        $this->setCanvasHeight($args['height']);
        $this->setPosition($args['position']);
        $this->setBgColors($args['bgcolor']);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
        $canvasBox = new Box(
            $this->getCanvasWidth(),
            $this->getCanvasHeight()
        );
        $filter = new \Imagine\Filter\Advanced\Canvas(
            new \Imagine\Imagick\Imagine(),
            $canvasBox,
            $this->getImageBoxPoint($canvasBox, $image->getSize()),
            $image->palette()->color($this->translateColorArray($this->getBgColors()))
        );
        return $filter->apply($image);
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
