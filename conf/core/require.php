<?php
/**
 * Schoolbox global dependencies
 *
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../www/conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/constants.php';

// TODO what does this do?
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());

require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();
