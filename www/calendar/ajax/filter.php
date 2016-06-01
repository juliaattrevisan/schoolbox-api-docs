<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// don't bother trying if fList isn't set
if(!(isset($_POST['fList']) && isset($_POST['view']))) {
    die('Not enough data supplied');
}

// this is just to properly initialise the sessions so changes will take hold
require_once dirname(__FILE__) . '/../../../library/Bootstrap.php';
$page = new SitePage();
$calHandler = new Calendar();

$response = $calHandler->updateFilters($_POST['fList'], $_POST['view']);

die($response ? 'Updated successfully' : 'Error updating');
