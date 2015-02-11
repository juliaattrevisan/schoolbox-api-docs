<?php
/**
 * Alaress_Image_Filetype_Gif
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Alaress\Image\Implementation\Imagine\Filetype;

class Gif extends BaseFiletype
{
    protected $format = 'gif';

    protected function outputSpecificHeaders()
    {
        header('Content-type: image/gif');
    }
}
