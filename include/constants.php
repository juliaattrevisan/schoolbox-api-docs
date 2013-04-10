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

define('HOMEPAGE_SERVICE',    1);
define('CALENDAR_SERVICE',    2);
define('NEWS_SERVICE',        3);
define('TUTORIAL_SERVICE',    4);
define('CMS_SERVICE',         5);
define('HOUSEMASTER_SERVICE', 6);

define('DENY_ACCESS',  0);
define('READ_ACCESS',  1);
define('WRITE_ACCESS', 2);
define('FULL_ACCESS',  3);

define('GROUP_TYPE_RESTRICTED', 1);
define('GROUP_TYPE_MODERATED',  2);
define('GROUP_TYPE_FREE',       3);

define('DEBUG_SQL_ENABLED', false);


/**
 * http://stackoverflow.com/questions/173400/php-arrays-a-good-way-to-check-if-an-array-is-associative-or-sequential
 * Use sparingly: it's expensive.
 * @param $array
 * @return bool
 */
function is_assoc(array $array) {
    return (bool)count(array_filter(array_keys($array), 'is_string'));
}