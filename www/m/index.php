<?php
/**
 * Landing page for SBM
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

if(!Cfg::get('mobile_enabled')) {
    SitePage::localRedir('/');
}

// instantiate mediator
$mediator = new Mediator();

// output and rinse hands.
$mediator->outputResponse();
