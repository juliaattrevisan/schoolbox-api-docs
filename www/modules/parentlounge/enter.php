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

$tassID = substr($_SESSION['synergy_id'], 1);

$token = $sso->getUserToken(array('sso_code' => $tassID));

// regardless of what happens, redirect the user somewhere
if($token) {
    $destination = "https://tassportal.cathedral.qld.edu.au/ParentLounge/PIALogin.cfm?sso_token=$token";
    header("Location: $destination");
}
else {
    SitePage::localRedir('/');
}