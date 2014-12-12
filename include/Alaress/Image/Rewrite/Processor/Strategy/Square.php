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

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Intervention\Image\Image;

class Square extends BaseStrategy implements StrategyInterface
{

    protected $innerProcessor = null;

    /**
    * Constructor
    *
    * @param $args
    */
    public function __construct($args)
    {
        $args['height'] = $args['width'];
        $this->innerProcessor = new Rectangle($args);
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(Image $image)
    {
        return $this->innerProcessor->manipulate($image);
    }
}
