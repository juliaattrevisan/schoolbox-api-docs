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

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Imagine\Image\Fill;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

class ConstrainKeepCanvas extends BaseStrategy
{
    /**
     * A constrain processor
     *
     * @var BaseStrategy
     */
    protected $contstrainProcessor;

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
        $this->contstrainProcessor = new Constrain($args, $this->getImageManager());
        $this->placementProcessor  = new Placement($args, $this->getImageManager());
    }

    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        $image = $this->contstrainProcessor->manipulate($image);
        $image = $this->placementProcessor->manipulate($image);
        return $image;
    }
}
