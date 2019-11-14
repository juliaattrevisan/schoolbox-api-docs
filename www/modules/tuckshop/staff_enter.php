<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// enforce SitePage context for authentication
if(!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/modules/tuckshop/staff_enter');
}

// mask this from all installs but Cathedral
if(Config::get('site_location') != 'cathedral') {
    $this->pageNotFound();
}

$tassID = strtoupper($_SESSION['username']);
$hash = md5('L@goon1917'.$tassID);

$destination = "https://msawebportal.cathedral.qld.edu.au/?id=$tassID&key=$hash";
header("Location: $destination");

// Don't continue processing if a redirect has been initiated. Since no template has been set,
// that will ultimately throw exceptions in SitePage's template renderer.
exit();
