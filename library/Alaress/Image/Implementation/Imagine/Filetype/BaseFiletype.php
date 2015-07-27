<?php
/**
 * Defines a base for image filetypes, and defines a create method which throws
 * exception if image isn't found.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Implementation\Imagine\Filetype;

use Alaress\Image\Filetype\FiletypeInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Imagick\Imagine;
use League\Flysystem;

abstract class BaseFiletype implements FiletypeInterface
{
    /**
     * A format (gif, png, jpg)
     *
     * @var string
     */
    protected $format = '';

    /**
     * @var ImagineInterface
     */
    protected $imageManager;


    /**
     * TODO inject Imagine instead of building it here
     */
    public function __construct()
    {
        $this->imageManager = new Imagine();
        $this->imageManager->setMetadataReader(new ExifMetadataReader());
    }

    /**
     * Create an image
     *
     * @param Flysystem\File $file
     * @return ImageInterface
     */
    public function loadImageResource(Flysystem\File $file)
    {
        return $this->getImageManager()->read($file->readStream());
    }

    /**
     * Save image to file system
     *
     * @param ImageInterface $image
     * @param Flysystem\File $file
     */
    public function save(ImageInterface $image, Flysystem\File $file)
    {
        $options = array();
        if ($image->layers()->count() > 1) {
            $options['animated'] = true;
            $options['animated.loops'] = 0;
        }
        try {
            $file->write($image->get($this->getFormat(), $options));
        } catch (Flysystem\FileExistsException $e) {
            //If it exists, thats ok we can continue
        }
    }

    /**
     * Outputs headers that are generic to all file types.
     */
    protected function outputHeaders()
    {
        header('Cache-Control: max-age=10800, private');
        header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
        header('Pragma: private');
        $this->outputSpecificHeaders();
    }

    /**
     * Outputs headers that are specific to a particular file type.
     */
    protected function outputSpecificHeaders()
    {
    }

    /**
     * Stream image to browser directly
     *
     * @param ImageInterface $image
     */
    public function stream($image)
    {
        // Assume anything layered is animated
        if ($image->layers()->count() > 1) {
            $output = $image->get($this->getFormat(), ['animated' => true]);
        } else {
            $output = $image->get($this->getFormat());
        }
        header('Content-length: ' . strlen($output));
        $this->outputHeaders();
        die($output);
    }

    /**
     * @return ImagineInterface
     */
    public function getImageManager()
    {
        return $this->imageManager;
    }

    /**
     * @param  ImagineInterface $imageManager
     *
     * @return $this
     */
    public function setImageManager($imageManager)
    {
        $this->imageManager = $imageManager;
        return $this;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param  string $format
     *
     * @return $this
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }
}
