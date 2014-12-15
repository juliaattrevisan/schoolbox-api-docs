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

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

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
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
        // TODO preserve proportions
        return $image->thumbnail(
            new Box(
                $this->getCanvasWidth(),
                $this->getCanvasHeight()
            )
        );
    }
}
