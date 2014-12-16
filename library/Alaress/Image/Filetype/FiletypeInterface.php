<?php
/**
 * Interface for image file types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Filetype;

interface FiletypeInterface
{
    /**
     * Create an image.
     *
     * @param  string $filename
     * @throws \Exception_Schoolbox_NotFound if file can't be read
     * @return mixed An image object instance, or a resource.
     *               TODO write an adapter class to tie together implementations
     */
    public function create($filename);

    /**
     * Loads an image.
     *
     * @param  string $filename
     * @return mixed An image object instance, or a resource.
     */
    public function loadImageResource($filename);

    /**
     * Streams an image from an image object, and then exits.
     *
     * @param mixed $image
     */
    public function stream($image);

    /**
     * Streams an image file, and exits.
     *
     * @param string $filename
     */
    public function streamFile($filename);

    /**
     * Saves an image.
     *
     * @param mixed  $image
     * @param string $filename
     */
    public function save($image, $filename);
}
