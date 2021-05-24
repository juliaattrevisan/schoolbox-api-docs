<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

/**
 * Class Redirect
 *
 * @package Schoolbox\Learning\Assessment
 */
class Redirect
{

    /**
     * @const string GET parameter
     */
    public const RETURN_URL = 'returnUrl';


    /**
     * Encode a url
     *
     * @param string $url
     * @return string
     */
    public static function encode($url)
    {
        return urlencode(base64_encode($url));
    }


    /**
     * Decode an encoded redirect url
     *
     * @param string $encoded
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
     * @param string $url
     * @return string
     */
    public static function queryString($url)
    {
        return '?' . self::RETURN_URL . '=' . self::encode($url);
    }


    /**
     * Fabricate a querystring param representation where the key/value pair is arbitrary
     * It's not prefixed with ? so you can use it anywhere in a querystring
     *
     * @param string $encodedUrl
     * @return string
     */
    public static function queryStringEncodedParam(string $encodedUrl): string
    {
        return self::RETURN_URL . '=' . $encodedUrl;
    }
}
