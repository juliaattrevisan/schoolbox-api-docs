<?php
/**
 * Alaress_Image_Transformation_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;


class BaseTransformation implements Transformation
{
    // Transform methods
    protected const METHOD_SQUARE    = 'Square';
    protected const METHOD_CONSTRAIN = 'Constrain';
    protected const METHOD_RECTANGLE = 'Rectangle';


    /** @var array */
    protected $allTransformations = array();

    /**
    * Get all transformations for a particular key
    *
    * @param  string $key
    * @throws \OutOfBoundsException if transformation isn't found
    * @return array
    */
    public function retrieve($key)
    {
        if (array_key_exists($key, $this->getAllTransformations())) {
            return $this->allTransformations[$key];
        }

        throw new \OutOfBoundsException('Could not load transformation list.');
    }

    /**
    * Get all transformations
    *
    * @return array
    */
    protected function getAllTransformations()
    {
        return $this->allTransformations;
    }

    /**
    * Get a list of transformation keys
    *
    * @return array
    */
    public function getAllTransformationNames()
    {
        return array_keys($this->getAllTransformations());
    }
}
