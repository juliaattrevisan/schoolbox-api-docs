<?php
/**
 * Alaress_Image_Filetype_Png
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Intervention\Filetype;

use Intervention\Image\Image;

class Png extends BaseFiletype
{
    protected $quality = 6;

    protected function outputSpecificHeaders()
    {
        header('Content-type: image/png');
    }
}
