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
     * @var Base|null
     */
    protected ?Base $next = null;


    /**
     * Test with next strategy in the sequence
     *
     * @return array|null
     */
    protected function testNext(): ?array
    {
        if ($this->getNext()) {
            return $this->getNext()->test();
        }

        return null;
    }


    /**
     * Each subclass must execute this method
     *
     * @return array|null
     */
    abstract public function test(): ?array;


    /* Getters/Setters
     */

    /**
     * Set next
     *
     * @param Base|null $next
     */
    public function setNext(?Base $next): void
    {
        $this->next = $next;
    }


    /**
     * Get next
     *
     * @return Base|null
     */
    public function getNext(): ?Base
    {
        return $this->next;
    }
}
