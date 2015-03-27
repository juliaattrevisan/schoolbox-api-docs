<?php
/**
 * Alaress\Image\Cache\Filesystem
 * Load images from some arbitrary path on the filesystem
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Cache;

use Cfg;

class Filesystem extends BrokenFS
{

    /**
     * Original image location
     *
     * @var string
     */
    protected $originalsLocation;

    /**
     * Constructor
     * Remembers what image you are trying to load from cache
     *
     * @param $filename
     * @param $key
     * @param $originalsLocation
     */
    public function __construct($filename, $key, $originalsLocation)
    {
        $this->setFilename($filename);
        $this->setKey($key);
        $this->setCacheLocation(Cfg::get('thumbnail_dir'));
        $this->setOriginalsLocation($originalsLocation);
    }

    /**
     * @return mixed
     */
    protected function getCachedFilename()
    {
        return md5($this->getFilename()) . '-' . $this->getKey() . '.dat';
    }


    /**
     * Set original image location
     *
     * @param string $originalsLocation
     */
    public function setOriginalsLocation($originalsLocation)
    {
        $this->originalsLocation = $originalsLocation;
    }

    /**
     * Get original image location
     *
     * @return string
     */
    public function getOriginalsLocation()
    {
        return $this->originalsLocation;
    }


    /**
     * @return string
     */
    protected function getStandardiseDir()
    {
        return $this->getOriginalsLocation();
    }

}
