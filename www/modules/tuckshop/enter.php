<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// enforce SitePage context for authentication
if(!isset($this)) {
    require_once('../../../include/Bootstrap.php');
    SitePage::localRedir('/modules/tuckshop/enter');
}

// mask this from all installs but Cathedral
if(Cfg::get('site_location') != 'cathedral') {
    $this->pageNotFound();
}

$tassID = $_SESSION['sid'];
$hash = md5('L@goon1917'.$tassID);

$destination = "https://msawebportal.cathedral.qld.edu.au/?id=$tassID&key=$hash";
header("Location: $destination");
