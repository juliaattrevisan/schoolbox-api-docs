<?php

/**
 * Decoder
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Registry\Decoder;

abstract class Base
{

    /**
     * Decode the url, distilling the actual url from some
     * encapsulated structure
     *
     * @param string $url
     * @return string
     */
    abstract public function decode($url);


    /**
     * Get the id out of the url
     *
     * @param string $url
     * @return int|null
     */
    protected function isThisDecoder($url)
    {
        $id = null;

        preg_match($this->getRegex(), $url, $matches);

        if (count($matches)) {
            $id = (int)$matches[1];
        }

        return $id;
    }

    /* Getters/Setters
     */


    /**
     * Get the regex to use when deciding which decoder to use
     *
     * @return string
     */
    abstract protected function getRegex();
}
