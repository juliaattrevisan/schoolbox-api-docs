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

namespace Alaress\Image\Implementation\Intervention\Processor\Strategy;

use Intervention\Image\Image;

class RotateOrientation extends BaseStrategy
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
     *
     * @var Image $image
     */
    public function manipulate($image)
    {
        return $image->orientate();
//        $exif = @exif_read_data($this->filePath);
//        if (!empty($exif['Orientation'])) {
//            switch($exif['Orientation']) {
//                case 8:
//                    $image = imagerotate($image, 90, 0);
//                    break;
//                case 3:
//                    $image = imagerotate($image, 180, 0);
//                    break;
//                case 6:
//                    $image = imagerotate($image, -90, 0);
//                    break;
//            }
//        }
//        return $image;
    }
}
