<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy;


abstract class Base {

    /**
     * Next in the chain
     *
     * @var Base
     */
    protected $next;

    /**
     * Test with next strategy in the sequence
     *
     * @return mixed
     */
    protected function testNext() {
        if ($this->getNext()) {
            return $this->getNext()->test();
        }
    }

    /* Getters/Setters
     */

    /**
     * Set next
     *
     * @param  $next
     */
    public function setNext($next) {
        $this->next = $next;
    }

    /**
     * Get next
     *
     * @return bool
     */
    public function getNext() {
        return $this->next;
    }
}