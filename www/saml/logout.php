<?php

/**
 * When the user visits this URL, the browser will be redirected to the SLO
 * IdP with an SLO request.
 */

require 'saml-setup.php';

use Alaress\Lumberjack\Logger;
use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Error\Mapper as ErrorMapper;

/** @var Kernel $kernel */
/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

/** @var array $settingsInfo */
$auth = new OneLogin\Saml2\Auth($settingsInfo);
$logger = new Logger(STDOUT, STDOUT, Config::get('saml_debug') ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO, [], true, 'saml');

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
    $logger->debug("Destroying local session");
    session_regenerate_id(true);
    session_destroy();
    $logger->debug("Attempting SLO: " . json_encode(array('nameId' => $nameId, 'sessionIndex' => $sessionIndex, 'nameIdFormat' => $nameIdFormat)));
    $auth->logout(null, [], $nameId, $sessionIndex, false, $nameIdFormat);
} catch (OneLogin\Saml2\Error $e) {
    $logger->error("SLO Error: " . $e->getMessage());
    error(ErrorMapper::ERROR_PROCESSING, $e->getMessage());
}
