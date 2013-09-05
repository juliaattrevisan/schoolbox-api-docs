<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

echo "[".date('Y-m-d H:i:s')."] Running health check script\n";

require_once(dirname(__FILE__) . '/../include/Bootstrap.php');

$siteLocation = Cfg::get('site_location');

// check for Saint Something as best we can in a simple fashion
if(preg_match('/^st([^aeiouyr])/', $siteLocation, $matches) > 0) {
    // uppercase the 3rd character (eg stmichaels to stMichaels)
    $siteLocation[2] = strtoupper($siteLocation[2]);
}

$sync = new Healthcheck(ucfirst($siteLocation));

$sync->run();
