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
     * @const string GET parameter
     */
    const RETURN_URL = 'returnUrl';

    /**
     * Encode a url
     *
     * @param $url
     * @return string
     */
    public static function encode($url)
    {
        return urlencode(base64_encode($url));
    }

    /**
     * Decode an encoded redirect url
     *
     * @param $encoded
     * @return string
     */
    public static function decode($encoded)
    {
        return base64_decode(urldecode($encoded));
    }

    /**
     * Fabricate a querystring param representation so we don't have to
     * concatenate all day, every day in controllers
     *
     * @param $url
     * @return string
     */
    public function queryString($url)
    {
        return '?' . self::RETURN_URL . '=' . $this->encode($url);
    }
}