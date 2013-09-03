<?php
/**
 * Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Image_Processor_Standard extends Alaress_Image_Processor_Abstract {

    /**
     * Constructor
     *
     * @param $cache
     * @param $transformations
     */
    public function __construct($cache, $transformations) {
        $this->setCache($cache);
        $this->setTransformations($transformations);
    }

    /**
     * Load an image, figure out how to render/manipulate it
     *
     * Supports chained methods
     *
     * @return $this
     */
    public function load() {
        $imageArray = $this->getCache()->load();

        /* Figure out what kind of file it is
         */
        $this->setFiletypeStrategy($imageArray);

        /* Prepare an image resource for chained manipulation
         */
        if ($this->getCache()->isLoadedFromCache()) {
            $this->getFiletypeStrategy()->streamFile($imageArray['filepath']);
        }
		else {
            $this->setImage($this->getFiletypeStrategy()->create($imageArray['filepath']));
        }

        return $this;
    }

    /**
     * Select an image modification strategy, and supply it with any
     * desired arguments in an associative array
     *
     * Supports chained methods
     *
     * @param $strategy
     * @param null $args
     * @return $this
     */
    private function loadProcessorStrategy($strategy, $args = null) {
        /** @var Alaress_Image_Processor_Strategy_Interface $processor */
        $strategyName = $this->getProcessorStrategyPrefix() . $strategy;
        $processor    = new $strategyName($args);
        $processor->setSupportsAlphaChannel($this->getFiletypeStrategy()->supportsAlphaChannel());
        $this->setProcessorStrategy($processor);

        return $this;
    }

    /**
     * Delegate method
     *
     * Use processor strategy to manipulate image resource and remember
     * the resultant image resource
     *
     * Supports chained methods
     *
     * @return $this
     */
    private function manipulate() {
        $manipulatedImage = $this->getProcessorStrategy()->manipulate($this->getImage());

        $this->setImage($manipulatedImage);

        return $this;
    }

    /**
     * Perform all transformations if we didn't load an image from the cache.
     * Otherwise transformations were done previously and saved, and we can skip
     * this step entirely
     *
     * @return $this
     */
    public function transformAll() {

        /* Retrieve the list of transformations and do 'em, if we haven't already
         * done them at some point in the past
         */
        if (!$this->getCache()->isLoadedFromCache()) {
            $allTransformations = $this->getTransformations()->retrieve($this->getCache()->getKey());

            /* Do it
             */
            foreach ($allTransformations as $currentTransformation) {
                $this->transform($currentTransformation['method'], $currentTransformation['args']);
            }
        }

        return $this;
    }

    /**
     * Facade method
     *
     * Combines loadProcessorStrategy() and manipulate() to support chained
     * methods in an easy-to-ready set of statements
     *
     * Supports chained methods
     *
     * @param $strategy
     * @param null $args
     * @return $this
     */
    public function transform($strategy, $args = null) {
        $this->loadProcessorStrategy($strategy, $args);
        $this->manipulate();

        return $this;
    }

    /**
     * Delegate method
     *
     * Cache then stream the manipulated image
     */
    public function stream() {
        $this->save();
        $this->getFiletypeStrategy()->streamFile($this->getCache()->getCachedFileLocation());
    }

    /**
     * Saves the transformed image
     * @return $this
     */
    public function save() {
        $this->getFiletypeStrategy()->save($this->getImage(), $this->getCache()->getCachedFileLocation());
        return $this;
    }

    /**
     * Gets the filepath for processed image output
     * @return string
     */
    public function getCachedFileLocation() {
        return $this->getCache()->getCachedFileLocation();
    }
}
