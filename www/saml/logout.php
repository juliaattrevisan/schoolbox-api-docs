<?php

/**
 * When the user visits this URL, the browser will be redirected to the SLO
 * IdP with an SLO request.
 */

require 'saml-setup.php';

use Schoolbox\Error\Mapper as ErrorMapper;

$page = new SitePage();

$auth = new OneLogin_Saml2_Auth($settingsInfo);

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
    $auth->logout(null, [], $nameId, $sessionIndex, false, $nameIdFormat);
} catch (OneLogin_Saml2_Error $e) {
    error(ErrorMapper::ERROR_PROCESSING, $e->getMessage());
}