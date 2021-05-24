<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Random;

use ArrayIterator;
use InfiniteIterator;
use LengthException;

/**
 * Generator for a predetermined set of values.
 *
 * This generator will cycle through all values within $values.
 */
class PredeterminedGenerator implements IGenerator
{
    /**
     *  A list of values to cycle through, forever.
     *
     * @var mixed[]|InfiniteIterator
     */
    private $values;

    /**
     * Constructor
     *
     * @param mixed[] $values
     * @throws LengthException if $values is empty
     */
    public function __construct(array $values)
    {
        if (count($values) === 0) {
            throw new LengthException('$values may not be an empty array');
        }

        $this->values = new InfiniteIterator(new ArrayIterator($values));
        $this->values->rewind();
    }

    /**
     * @return mixed
     */
    public function generate()
    {
        $return = $this->values->current();
        $this->values->next();
        return $return;
    }
}
