<?php
/**
 * Defines a base for image filetypes, and defines a create method which throws
 * exception if image isn't found.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Rewrite\Imagine\Filetype;

use Alaress\Image\Filetype\FiletypeInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Imagick\Imagine;

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

    public function __construct()
    {
        // TODO inject this instead of building it here
        $this->imageManager = new Imagine();
        $this->imageManager->setMetadataReader(new ExifMetadataReader());
    }

    /**
    * Creates an image using the loadImageResource() method of the subclass.
    *
    * @param  $filename
    * @throws \Exception_Schoolbox_NotFound if file can't be read
    * @return ImageInterface
    */
    public function create($filename)
    {
        $im = false;
        if (is_file($filename) && is_readable($filename)) {
            $im = $this->loadImageResource($filename);
        }
        if (!$im) {
            throw new \Exception_Schoolbox_NotFound(sprintf('Cannot read file "%s"', $filename));
        }
        return $im;
    }

    /**
     * Create an image
     *
     * @param string $filename
     * @return ImageInterface
     */
    public function loadImageResource($filename)
    {
        return $this->getImageManager()->open($filename);
    }

    /**
     * Save image to file system
     *
     * @param ImageInterface $image
     * @param string         $filename
     */
    public function save($image, $filename)
    {
        $options = array();
        if ($image->layers()->count() > 1) {
            $options['animated'] = true;
            $options['animated.loops'] = 0;
        }
        file_put_contents(
            $filename,
            $image->get($this->getFormat(), $options)
        );
//        if ($image->layers()->count() > 1) {
//            // Awful hack to make animated GIFs loop, because imagick is buggy
//            $filearg = escapeshellarg($filename);
//            exec("convert $filearg -loop 0 $filearg");
//        }
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
        $this->outputHeaders();
        echo $image->get($this->getFormat());
        exit;
    }

    /**
     * {@inheritdoc}
     */
    public function streamFile($filename)
    {
        $this->outputHeaders();
        header('Content-length: ' . filesize($filename));
        header('Last-Modified: ' . date('r', filemtime($filename)));
        readfile($filename);
        exit;
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
