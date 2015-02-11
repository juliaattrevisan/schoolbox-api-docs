<?php
/**
 * Alaress_Image_Processor_Strategy_Square
 *
 * Constrain an image to fit within a specified width and height which is measured
 * from the center of the source image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Imagine\Image\ImageInterface;

class Square extends BaseStrategy
{
    /**
     * @var BaseStrategy
     */
    protected $rectangleProcessor = null;

    /**
     * {@inheritdoc}
     */
    protected function setup($args)
    {
        $args['height'] = $args['width'];
        $this->rectangleProcessor = new Rectangle($args, $this->getImageManager());
    }

    /**
     * {@inheritdoc}
     *
     * @var ImageInterface $image
     */
    public function manipulate($image)
    {
        return $this->rectangleProcessor->manipulate($image);
    }
}
