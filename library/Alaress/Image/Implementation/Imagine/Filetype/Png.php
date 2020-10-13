<?php
/**
 * Alaress_Image_Filetype_Png
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Implementation\Imagine\Filetype;

class Png extends BaseFiletype
{
    protected function outputSpecificHeaders()
    {
        header('Content-type: image/png');
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return 'png';
    }
}
