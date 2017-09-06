<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Random;

use ArrayIterator;
use InfiniteIterator;
use LengthException;

/**
 * Generator for a pseudo-random number.
 */
class Predetermined implements IGenerator
{
    /**
     *  A list of values to cycle through, forever.
     *
     * @var float[]|InfiniteIterator
     */
    private $values;

    /**
     * Constructor
     *
     * @param float[] $values
     * @throws LengthException if $values is empty
     */
    public function __construct($values)
    {
        if (count($values) === 0) {
            throw new LengthException('$values may not be an empty array');
        }

        $this->values = new InfiniteIterator(new ArrayIterator($values));
        $this->values->rewind();
    }

    /**
     * @return float
     */
    public function random()
    {
        $return = $this->values->current();
        $this->values->next();
        return $return;
    }
}
