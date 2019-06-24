<?php
/**
 *
 * Collection of global constants
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

define('DEBUG_SQL_ENABLED', false);
define('MOBILE_SESSION_TIMEOUT', 31536000); // hr * day * year = 3600 * 24 * 365 = 31,536,000


/**
 * http://stackoverflow.com/questions/173400/php-arrays-a-good-way-to-check-if-an-array-is-associative-or-sequential
 * Use sparingly: it's expensive.
 * @param $array
 * @return bool
 */
function is_assoc(array $array) {
    return (bool)count(array_filter(array_keys($array), 'is_string'));
}