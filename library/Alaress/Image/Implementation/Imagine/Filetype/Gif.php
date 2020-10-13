<?php
/**
 * Alaress_Image_Filetype_Gif
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Alaress\Image\Implementation\Imagine\Filetype;

class Gif extends BaseFiletype
{
    protected function outputSpecificHeaders()
    {
        header('Content-type: image/gif');
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return 'gif';
    }
}
