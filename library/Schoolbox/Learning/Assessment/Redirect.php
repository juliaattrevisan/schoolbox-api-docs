<?php
/**
 * Redirect
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


class Redirect
{

    /**
     * Encode a url
     *
     * @param $url
     * @return string
     */
    public function encode($url)
    {
        return urlencode(base64_encode($url));
    }

    /**
     * Decode an encoded redirect url
     *
     * @param $encoded
     * @return string
     */
    public function decode($encoded)
    {
        return base64_decode(urldecode($encoded));
    }
}