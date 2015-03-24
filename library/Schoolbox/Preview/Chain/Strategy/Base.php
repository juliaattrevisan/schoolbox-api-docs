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


class Base {

    /**
     * Next in the chain
     *
     * @var Base
     */
    protected $next;

    /**
     * The url to test
     *
     * @var string
     */
    protected $url = '';

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {
        $this->setUrl($url);
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
     * Set url
     *
     * @param string $url
     */
    protected function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    protected function getUrl()
    {
        return $this->url;
    }
}