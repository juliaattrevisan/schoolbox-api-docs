<?php
/**
 * Alaress_Image_Filetype_Interface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Rewrite\Intervention\Filetype;

use Intervention\Image\Image;

interface FiletypeInterface
{

    /**
     * Create an image
     *
     * @param $filename
     * @throws \Exception_Schoolbox_NotFound if file can't be read
     * @return resource
     */
    public function create($filename);

    public function loadImageResource($filename);

    public function stream(Image $image);

    public function streamFile($filename);

    /**
     * Save image to file system
     *
     * @param Image  $image
     * @param string $filename
     */
    public function save(Image $image, $filename);

    /**
     * Does this image type support the alpha (transparency) channel?
     *
     * @return bool
     */
    public function supportsAlphaChannel();
}
