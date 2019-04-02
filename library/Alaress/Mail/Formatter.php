<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://www.alaress.com.au/
 */

namespace Alaress\Mail;


use Scrub;

class Formatter
{
    /**
     * Format folder header or also know as subject name
     * E.g: English 3 4 (EN344)
     *
     * @param $name
     * @param $code
     *
     * @return string
     */
    public static function header($name, $code): string
    {
        return Scrub::text($name) . ($code ? ' (' . $code . ')' : '');
    }
}
