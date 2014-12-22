<?php
/**
 * Alaress_Image_Portrait
 *
 * Facade class for Alaress_Image_Processor and Alaress_Image_Cache implementation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image;

class Portrait
{

    /**
     * @var Processor\Standard
     */
    protected $imageProcessor = null;

    /**
     * Constructor
     *
     * Sets up the caching implementation, image processor and streams it
     *
     * @param $externalUserId
     * @param $key
     * @param $transformations
     */
    public function __construct($externalUserId, $key, $transformations)
    {

        /* Choose a caching method to use
         */
        $cache = new Cache\Portrait($externalUserId, $key);
        $cache->purgeOldTransformations();

        /* Process and stream image
         */
        $this->imageProcessor = new Processor\Standard(
            $cache,
            $transformations,
            new \Alaress\Image\Implementation\Imagine\Filetype\Factory(),
            new \Alaress\Image\Implementation\Imagine\Processor\Strategy\Factory()
        );
        $this->imageProcessor->load()->transformAll();
    }

    /**
     * Outputs the image
     */
    public function stream()
    {
        $this->imageProcessor->stream();
    }

    /**
     * Saves the image
     * @return string Location of saved image
     */
    public function save()
    {
        $this->imageProcessor->save();
        return $this->imageProcessor->getCachedFileLocation();
    }

    /**
     * Sets the image processor
     * @param Processor\Standard $imageProcessor
     */
    private function setImageProcessor(Processor\Standard $imageProcessor)
    {
        $this->imageProcessor = $imageProcessor;
    }

    /**
     * Gets the image processor
     * @return Processor\Standard
     */
    private function getImageProcessor()
    {
        return $this->imageProcessor;
    }
}
