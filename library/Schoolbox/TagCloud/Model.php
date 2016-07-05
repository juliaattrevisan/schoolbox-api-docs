<?php
/**
 * Tag Cloud Model
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\TagCloud;

use Schoolbox\User;

class Model {

    /** @var int */
    protected $id;

    /** @var string */
    protected $value;

    /**
     * Model constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param string $value
     * @return self
     */
    public function setValue($value) {
        $this->value = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue() {
        return $this->value;
    }
}