<?php
/**
 * Filetype factory for the original image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Filetype;

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

    public function stream($image);

    public function streamFile($filename);

    public function save($image, $filename);

    /**
     * Does this image type support the alpha (transparency) channel?
     *
     * @return bool
     */
    public function supportsAlphaChannel();
}
