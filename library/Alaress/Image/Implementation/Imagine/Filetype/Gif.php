<?php
/**
 * Alaress_Image_Filetype_Gif
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
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
