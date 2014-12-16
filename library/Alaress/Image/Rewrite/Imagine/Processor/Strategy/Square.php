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

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Imagine\Image\ImageInterface;

class Square extends BaseStrategy implements StrategyInterface
{
    /**
     * @var StrategyInterface
     */
    protected $rectangleProcessor = null;

    /**
    * Constructor
    *
    * @param $args
    */
    public function __construct($args)
    {
        $args['height'] = $args['width'];
        $this->rectangleProcessor = new Rectangle($args);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
        return $this->rectangleProcessor->manipulate($image);
    }
}
