<?php
/**
 * Unit tests for Alaress\Date\Interval class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Test;
use Iterator;
use JsonSerializable;

class Traversable implements Iterator, JsonSerializable {
    protected $position = 0;

    protected $array = array();

    public function __construct(array $array) {
        $this->array = $array;
        $this->rewind();
    }

    public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->array[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        ++$this->position;
    }

    function valid() {
        return isset($this->array[$this->position]);
    }

    public function jsonSerialize() {
        return array(
            'label' => 'collection',
            'value' => $this->array,
        );
    }

}
