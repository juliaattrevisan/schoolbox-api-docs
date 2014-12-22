<?php
/**
 * Alaress_Image_Filesystem
 *
 * Facade class for Alaress_Image_Processor and Alaress_Image_Cache implementation
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Filesystem
{

    /**
    * Consructor
    * Sets up the caching implementation, image processor and streams it
    *
    * @param $filename
    * @param $key
    * @param $transformations
    */
    public function __construct($filename, $key, $transformations)
    {

        /* Choose a caching method to use
		 */
        $cache = new Alaress_Image_Cache_Filesystem($filename, $key);

        /* Process and stream image
		 */
        $imageProcessor = new Alaress_Image_Processor_Standard(
            $cache,
            $transformations,
            new \Alaress\Image\Rewrite\Imagine\Filetype\Factory(),
            new \Alaress\Image\Rewrite\Imagine\Processor\Strategy\Factory()
        );
        $imageProcessor->load()->stream();
    }
}
