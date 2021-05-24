<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy;

/**
 * Class Base
 *
 * The fundamentals of chain of responsibility nodes
 *
 * @package Schoolbox\Preview\Chain\Strategy
 */
abstract class Base
{

    /**
     * Next in the chain
     *
     * @var Base
     */
    protected $next;


    /**
     * Test with next strategy in the sequence
     *
     * @return array|null
     */
    protected function testNext()
    {
        if ($this->getNext()) {
            return $this->getNext()->test();
        }

        return null;
    }


    /**
     * Each subclass must execute this method
     *
     * @return mixed
     */
    abstract public function test();


    /* Getters/Setters
     */

    /**
     * Set next
     *
     * @param Base $next
     */
    public function setNext($next)
    {
        $this->next = $next;
    }


    /**
     * Get next
     *
     * @return Base
     */
    public function getNext()
    {
        return $this->next;
    }
}
