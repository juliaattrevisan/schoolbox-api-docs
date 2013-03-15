<?php
/**
 * Base collection of model objects.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class Collection implements \Iterator, \Countable {

    private $stack = array();
    private $position = 0;

    /**
     * @param $element
     */
    public function add($element) {
        $this->stack[] = $element;
    }

    /**
     * Constructor
     *
     * @param array $stack
     */
    public function __construct($stack) {
        $this->setStack($stack);
    }

    /**
     * Rewind
     */
    public function rewind() {
        $this->position = 0;
    }

    /**
     * Current
     *
     * @return mixed
     */
    public function current() {
        return $this->stack[$this->position];
    }

    /**
     * Key
     *
     * @return mixed
     */
    public function key() {
        return $this->position;
    }

    /**
     * Next
     */
    public function next() {
        ++$this->position;
    }

    /**
     * Valid
     *
     * @return bool
     */
    public function valid() {
        return isset($this->stack[$this->position]);
    }

    /* Getters/Setters
     */

    /**
     * Set stack
     *
     * @param $stack
     */
    protected function setStack($stack) {
        $this->stack = $stack;
    }

    /**
     * Get stack
     *
     * @return array
     */
    protected function getStack() {
        return $this->stack;
    }

    /**
     * Get number of elements in collection.
     *
     * @return array
     */
    public function count() {
        return count($this->getStack());
    }
}
