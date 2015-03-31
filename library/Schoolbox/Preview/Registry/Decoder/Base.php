<?php
/**
 * Decoder
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Registry\Decoder;


abstract class Base {

    /**
     * The regex to use when deciding which decoder to use
     *
     * @var string
     */
    protected $regex = '';

    /**
     * Decode the url, distilling the actual url from some
     * encapsulated structure
     *
     * @param $url
     * @return mixed
     */
    abstract public function decode($url);

    /**
     * Get the id out of the url
     *
     * @param $url
     * @return bool
     */
    protected function isThisDecoder($url) {
        $id = false;

        preg_match($this->getRegex(), $url, $matches);

        if (count($matches)) {
            $id = $matches[1];
        }

        return $id;
    }

    /* Getters/Setters
     */

    /**
     * Set regex
     *
     * @param string $regex
     */
    protected function setRegex($regex)
    {
        $this->regex = $regex;
    }

    /**
     * Get regex
     *
     * @return string
     */
    protected function getRegex()
    {
        return $this->regex;
    }
}