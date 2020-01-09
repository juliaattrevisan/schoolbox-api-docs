<?php

/**
 * When the user visits this URL, the browser will be redirected to the SLO
 * IdP with an SLO request.
 */

require 'saml-setup.php';

use Schoolbox\Error\Mapper as ErrorMapper;

$page = new SitePage();

$auth = new OneLogin\Saml2\Auth($settingsInfo);

$nameId = null;
$sessionIndex = null;
$nameIdFormat = null;

if (isset($_SESSION['samlNameId'])) {
    $nameId = $_SESSION['samlNameId'];
}
if (isset($_SESSION['samlSessionIndex'])) {
    $sessionIndex = $_SESSION['samlSessionIndex'];
}
if (isset($_SESSION['samlNameIdFormat'])) {
    $nameIdFormat = $_SESSION['samlNameIdFormat'];
}
try {
    SAMLDebugLog("Attempting SLO: ".json_encode(array('nameId'=>$nameId, 'sessionIndex'=>$sessionIndex, 'nameIdFormat'=>$nameIdFormat)));
    $auth->logout(null, [], $nameId, $sessionIndex, false, $nameIdFormat);
} catch (OneLogin\Saml2\Error $e) {
    SAMLDebugLog("SLO Error: ".$e->getMessage());
    error(ErrorMapper::ERROR_PROCESSING, $e->getMessage());
}
