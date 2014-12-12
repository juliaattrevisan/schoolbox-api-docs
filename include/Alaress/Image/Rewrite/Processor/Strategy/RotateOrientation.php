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

use Imagine\Image\ImageInterface;

class RotateOrientation extends BaseStrategy implements StrategyInterface
{

    private $filePath = '';

    /**
     * Constructor
     *
     * @param $args
     */
    public function __construct($args)
    {
        $this->filePath = $args['FilePath'];
    }

    /**
     * {@inheritdoc}
     */
    public function manipulate(ImageInterface $image)
    {
        $metadata = $image->metadata();
        $exif = @exif_read_data($metadata['filepath']);
        if (!empty($exif['Orientation'])) {
            switch($exif['Orientation']) {
                case 8:
                    $image->rotate(90);
                    break;
                case 3:
                    $image->rotate(180);
                    break;
                case 6:
                    $image->rotate(270);
                    break;
            }
        }
        return $image;
    }
}
