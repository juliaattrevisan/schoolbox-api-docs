<?php
/**
 * Alaress_Image_Processor_Freeform
 * This allows you to perform certain transformations dynamically, passing
 * arguments into the transformation defintion from the querystring.
 * This is accomplished via the Transformation class, demonstrated in MyImage.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Processor;

class Freeform extends Standard
{

    /**
     * The transform key to use, defaults to "Constrain".
     *
     * @var string
     */
    private $transform_key = 'Constrain';

    /**
     * @param  string $transform_key
     *
     * @return $this
     */
    public function setTransformKey(string $transform_key): self
    {
        $this->transform_key = $transform_key;
        return $this;
    }

    /**
     * Overwrite parent method of getting transform key from the cache
     *
     * @return string
     */
    protected function getTransformKey(): string
    {
        return $this->transform_key;
    }
}
