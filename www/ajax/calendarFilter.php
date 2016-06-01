<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


// don't bother trying if fList isn't set
if(!isset($_POST['fList'])) {
    die();
}

// this is just to properly initialise the sessions so changes will take hold
require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
$page = new SitePage();

foreach($_POST['fList'] as $filter) {
    $_SESSION['calendarFilter'][$filter[0]] = preg_replace('/[^0-9]/', '', $filter[1]);
}

die();
