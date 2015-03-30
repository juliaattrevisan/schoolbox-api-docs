<?php
/**
 * Interface for image file types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Filetype;

use Imagine\Image\ImageInterface;
use League\Flysystem;

interface FiletypeInterface
{

    /**
     * Loads an image.
     *
     * @param  Flysystem\File $file
     * @return ImageInterface
     */
    public function loadImageResource(Flysystem\File $file);

    /**
     * Streams an image from an image object, and then exits.
     *
     * @param mixed $image
     */
    public function stream($image);

    /**
     * Saves an image.
     *
     * @param ImageInterface $image
     * @param Flysystem\File $file
     */
    public function save(ImageInterface $image, Flysystem\File $file);
}
