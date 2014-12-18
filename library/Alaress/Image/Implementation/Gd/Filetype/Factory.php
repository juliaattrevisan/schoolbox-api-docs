<?php
/**
 * Filetype factory for the original image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Filetype;

class Factory implements FactoryInterface
{
    /**
     * Creates a filetype strategy.
     *
     * @param  array $imageArray
     * @return \FiletypeInterface
     * @throws \Exception_Schoolbox if image isn't recognised
     */
    public function build($imageArray)
    {
        switch ($imageArray['mime']) {
            case 'image/jpeg':
            case 'image/pjpeg':
                return new \Alaress_Image_Filetype_Jpg();
            case 'image/gif':
                return new \Alaress_Image_Filetype_Gif();
            case 'image/png':
            case 'image/x-png':
                return new \Alaress_Image_Filetype_Png();
            default:
                throw new \Exception_Schoolbox('Unknown image type.');
        }
    }
}
