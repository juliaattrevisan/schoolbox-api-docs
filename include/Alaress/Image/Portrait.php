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
class Alaress_Image_Portrait {

    /**
     * @var Alaress_Image_Processor_Abstract
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
    public function __construct($externalUserId, $key, $transformations) {

        /* Choose a caching method to use
         */
        $cache = new Alaress_Image_Cache_Portrait($externalUserId, $key);

        /* Process and stream image
         */
        $this->imageProcessor = new Alaress_Image_Processor_Standard($cache, $transformations);
        $this->imageProcessor->load()->transformAll();
    }

    /**
     * Outputs the image
     */
    public function stream() {
        $this->imageProcessor->stream();
    }

    /**
     * Saves the image
     * @return string Location of saved image
     */
    public function save() {
        $this->imageProcessor->save();
        return $this->imageProcessor->getCachedFileLocation();
    }

    /**
     * Sets the image processor
     * @param Alaress_Image_Processor_Abstract $imageProcessor
     */
    private function setImageProcessor(Alaress_Image_Processor_Abstract $imageProcessor) {
        $this->imageProcessor = $imageProcessor;
    }

    /**
     * Gets the image processor
     * @return Alaress_Image_Processor_Abstract
     */
    private function getImageProcessor() {
        return $this->imageProcessor;
    }

}
