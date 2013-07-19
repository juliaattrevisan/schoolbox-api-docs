<?php
/**
 * Alaress_Image_Storage
 *
 * Facade class for Alaress_Image_Processor and Alaress_Image_Cache implementation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Image_Storage {

	/**
	 * Consructor
	 * Sets up the caching implementation, image processor and streams it
	 *
	 * @param Schoolbox\File                        $resource
	 * @param string                                $key
	 * @param Alaress_Image_Transformation_Abstract $transformations
	 */
    public function __construct($resource, $key, $transformations) {

        /* Choose a caching method to use
         */
        $cache = new Alaress_Image_Cache_Storage($resource, $key);

        /* Process and stream image
         */
        $imageProcessor = new Alaress_Image_Processor_Standard($cache, $transformations);
        $imageProcessor->load()->transformAll()->stream();
    }
}
