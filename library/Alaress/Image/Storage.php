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

use Schoolbox;
use Schoolbox\Resource;
use Alaress\Image\Transformation\Transformation;
use Alaress\Image\Implementation\Imagine;

class Storage extends AbstractHandler
{
    /**
    * Sets up the caching implementation, image processor and streams it
    *
    * @param Resource\Storage\Model $resource
    * @param string                 $key
    * @param Transformation         $transformations
    * @throws \OutOfBoundsException
    */
    public function __construct(Resource\Storage\Model $resource, $key, Transformation $transformations)
    {
        // Test that requested transform exists before proceeding
        $transformations->retrieve($key);

        // Process the given storage object
        $this->initProcessor(new Cache\Storage($resource, $key), $transformations);
    }
}
