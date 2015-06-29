<?php
/**
 * Filetype factory for the Imagine image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Implementation\Imagine\Filetype;

use Alaress\Image\Filetype\FactoryInterface;

class Factory implements FactoryInterface
{
    /**
     * Creates a filetype strategy.
     *
     * @param  array $imageArray
     * @return BaseFiletype
     * @throws \Exception_Schoolbox if image isn't recognised
     */
    public function build($imageArray)
    {
        switch ($imageArray['mime']) {
            case 'image/jpeg':
            case 'image/pjpeg':
                return new Jpg();
            case 'image/gif':
                return new Gif();
            case 'image/png':
            case 'image/x-png':
                return new Png();
            default:
                throw new \Exception_Schoolbox('Unknown image type "'.$imageArray['mime'].'"');
        }
    }
}
