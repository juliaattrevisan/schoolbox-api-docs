<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

// initialise session
new SitePage(false);

// set a cookie to force a particular view
$agent = new HttpUserAgent();

if(isset($_GET['v'])) {
    if($_GET['v'] == 'mobile') {
        $agent->forceMobile();
    }
    elseif($_GET['v'] == 'desktop') {
        $agent->forceDesktop();
    }
    else {
        $agent->forceDefault();
    }
}

SitePage::localRedir('/');