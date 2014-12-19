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

namespace Alaress\Image;

class Storage
{
    /**
    * Consructor
    * Sets up the caching implementation, image processor and streams it
    *
    * @param Schoolbox\Storage                        $resource
    * @param string                                $key
    * @param Alaress_Image_Transformation_Abstract $transformations
    */
    public function __construct($resource, $key, $transformations)
    {

        /* Choose a caching method to use
         */
        $cache = new Cache\Storage($resource, $key);

        /* Process and stream image
         */
        $imageProcessor = new Processor\Standard(
            $cache,
            $transformations,
            new \Alaress\Image\Implementation\Imagine\Filetype\Factory(),
            new \Alaress\Image\Implementation\Imagine\Processor\Strategy\Factory()
        );
        $imageProcessor->load()->stream();
    }
}
