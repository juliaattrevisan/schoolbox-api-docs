<?php
/**
 * Alaress_Image_Processor_Strategy_RotateOrientation
 *
 * Images from iphones/ipads and some cameras have rotation information in the exif data
 * This transformation makes sure that the image is rotated to the correct orientation
 * before any other transformations take place.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Rewrite\Processor\Strategy;

use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\ImageInterface;

class RotateOrientation extends BaseStrategy implements StrategyInterface
{

    private $filePath = '';

    /**
     * Autorotate filter
     *
     * @var Autorotate
     */
    protected $filter;

    /**
     * Constructor
     *
     * @param $args
     */
    public function __construct($args)
    {
        $this->filePath = $args['FilePath'];
        $this->filter   = new Autorotate();
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
        return $this->filter->apply($image);
    }
}
