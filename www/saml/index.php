<?php
/**
 *
 * When the user visits this URL, the browser will be redirected to the SSO
 * IdP with an authorization request. If successful, it will then be
 * redirected to the consume URL (specified in settings) with the auth
 * details.
 */

require 'saml-setup.php';

$auth = new OneLogin\Saml2\Auth($settingsInfo);
$returnUrl = null;

// Include page as local URL (if it's set)
if (isset($_GET['page']) && preg_match('|^/|', $_GET['page'])) {
    $returnUrl = $_GET['page'];
}

$auth->login($returnUrl);
