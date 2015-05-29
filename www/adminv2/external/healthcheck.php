<?php
/**
 * External DB Healthcheck
 *
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (!$_SESSION['user']->isSuperuser()) {
	        error(19);
}

$siteLocation = Cfg::get('site_location');

// check for Saint Something as best we can in a simple fashion
if(preg_match('/^st([^aeiouyr])/', $siteLocation, $matches) > 0) {
    // uppercase the 3rd character (eg stmichaels to stMichaels)
    $siteLocation[2] = strtoupper($siteLocation[2]);
}

$sync = new Healthcheck(ucfirst($siteLocation));

echo "<pre>";
$sync->run();
echo "</pre>";
die;
