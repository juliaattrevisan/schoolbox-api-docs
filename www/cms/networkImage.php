<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

// a bit heavy-handed, but I guess we're not implementing file previews for network??
SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
