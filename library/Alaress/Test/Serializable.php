<?php
/**
 * Unit tests for Alaress\Date\Interval class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Test;
use JsonSerializable;

class Serializable implements JsonSerializable {

    protected $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function jsonSerialize() {
        return array(
            'label' => 'object',
            'value' => $this->value,
        );
    }

}
