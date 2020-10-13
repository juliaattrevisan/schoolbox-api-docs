<?php
/**
 * Alaress_Image_Filetype_Jpg
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Alaress\Image\Implementation\Imagine\Filetype;

class Jpg extends BaseFiletype
{
    protected function outputSpecificHeaders()
    {
        header('Content-type: image/jpeg');
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return 'jpg';
    }
}
