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
     * Decode the url, distilling the actual url from some
     * encapsulated structure
     *
     * @param $url
     * @return mixed
     */
    abstract public function decode($url);
} 