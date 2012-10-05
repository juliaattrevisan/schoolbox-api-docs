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
    SitePage::localRedir('/modules/parentlounge/enter');
}

$sso = new External_Tass_SSO();

// remove any non-numeric characters so it's the proper TASS id
$tassID = preg_replace('/[^0-9]/s', '', $_SESSION['sid']);

$token = $sso->getUserToken(array('sso_code' => $tassID));

// regardless of what happens, redirect the user somewhere (added bonus of silent fail on all other schools if they accidentally came across it)
if($token) {
    $destination = "https://tassportal.cathedral.qld.edu.au/ParentLounge/PIALogin.cfm?sso_token=$token";
    header("Location: $destination");
}
else {
    $this->pageNotFound();
}