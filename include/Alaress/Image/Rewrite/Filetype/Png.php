<?php
/**
 * Alaress_Image_Filetype_Png
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Filetype;

class Png extends BaseFiletype
{
    protected $format = 'png';

    protected function outputSpecificHeaders()
    {
        header('Content-type: image/png');
    }

    /**
     * PNG does support the alpha channel
     *
     * @return bool Always true
     */
    public function supportsAlphaChannel()
    {
        return true;
    }
}
