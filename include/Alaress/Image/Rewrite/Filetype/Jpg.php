<?php
/**
 * Alaress_Image_Filetype_Jpg
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Alaress\Image\Rewrite\Filetype;

class Jpg extends BaseFiletype
{
    protected function outputSpecificHeaders()
    {
        header('Content-type: image/jpeg');
    }

    /**
     * JPEG does not support the alpha channel
     *
     * @return bool Always false
     */
    public function supportsAlphaChannel()
    {
        return false;
    }
}
