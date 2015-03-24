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
     * Header data from get_headers()
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Constructor
     *
     * @param $headers
     */
    public function __construct($headers) {
        $this->setHeaders($headers);
    }

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

    /**
     * Macro method
     *
     * @return mixed
     */
    protected function findContentType() {
        return $this->findByKey('Content-Type');
    }

    /**
     * Get a header key
     *
     * @param $key
     * @return mixed
     */
    protected function findByKey($key) {
        $headers = $this->getHeaders();

        return $headers[$key];
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

    /**
     * Set headers
     *
     * @param array $headers
     */
    protected function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Get headers
     *
     * @return array
     */
    protected function getHeaders()
    {
        return $this->headers;
    }
}