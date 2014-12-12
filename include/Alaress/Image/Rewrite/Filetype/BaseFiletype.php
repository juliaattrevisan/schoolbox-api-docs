<?php
/**
 * Defines a base for image filetypes, and defines a create method which throws
 * exception if image isn't found.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Rewrite\Filetype;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;

abstract class BaseFiletype implements FiletypeInterface
{
    /**
     * @var ImageManager
     */
    protected $imageManager;

    public function __construct()
    {
        // TODO inject this instead of building it here
        $this->imageManager = new ImageManager();
    }

    /**
    * Creates an image using the loadImageResource() method of the subclass.
    *
    * @param  $filename
    * @throws \Exception_Schoolbox_NotFound if file can't be read
    * @return resource
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
     * @param $filename
     * @return resource
     */
    public function loadImageResource($filename)
    {
        //
        // Sadly silence operator is needed for all imagecreatefrom* methods,
        // otherwise error output corrupts image output.
        return $this->getImageManager()->make($filename);
    }

    /**
     * Save image to file system
     *
     * @param Image  $image
     * @param string $filename
     */
    public function save(Image $image, $filename)
    {
        $image->save($filename);
    }

    protected function outputHeaders()
    {
        header('Cache-Control: max-age=10800, private');
        header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
        header('Pragma: private');
        $this->outputSpecificHeaders();
    }

    protected function outputSpecificHeaders()
    {
    }

    /**
     * Stream image to browser directly
     *
     * @param $image
     */
    public function stream(Image $image)
    {
        $this->outputHeaders();
        echo $image->encode();
        exit;
    }

    public function streamFile($filename)
    {
        $this->outputHeaders();
        header('Content-length: ' . filesize($filename));
        header('Last-Modified: ' . date('r', filemtime($filename)));
        readfile($filename);
        exit;
    }

    /**
     * @return ImageManager
     */
    public function getImageManager()
    {
        return $this->imageManager;
    }

    /**
     * @param  ImageManager $imageManager
     *
     * @return $this
     */
    public function setImageManager($imageManager)
    {
        $this->imageManager = $imageManager;
        return $this;
    }
}
