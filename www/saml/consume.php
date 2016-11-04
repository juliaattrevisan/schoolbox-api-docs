<?php
/**
 * SAMPLE Code to demonstrate how to handle a SAML assertion response.
 *
 * The URL of this file will have been given during the SAML authorization.
 * After a successful authorization, the browser will be directed to this
 * link where it will send a certified response via $_POST.
 */

use Schoolbox\Error\Mapper as ErrorMapper;

$settings = null;
require 'settings.php';

$page = new SitePage(false);

/* Handle Single Logout commands */
if (isset($_POST['SAMLRequest'])) {
    SAMLDebugLog('SAMLRequest assertion received, assuming logout');
    SAMLDebugLog(base64_decode($_POST['SAMLRequest']));
    session_start();
    session_destroy();
    header('Location: ' . Config::get('saml_logout_url'));
    exit;
}

if(isset($_POST['SAMLResponse'])) {
    SAMLDebugLog(base64_decode($_POST['SAMLResponse']));

    try {
        $samlResponse = new OneLogin_Saml_Response($settings, $_POST['SAMLResponse']);

        if ($samlResponse->isValid()) {
            $sessionHandler = new Session();
            if ($sessionHandler->create($samlResponse->getNameId())) {
                $page = '/';
                if (isset($_POST['RelayState']) && preg_match('|^/|', $_POST['RelayState']) && substr($_POST['RelayState'], 0, 6) != '/login') {
                    $page = $_POST['RelayState'];
                }

                SAMLDebugLog("SAML auth successful");
                SitePage::localRedir($page);

            } else {
                $msg = 'Invalid SAML user.';
                echo $msg;
                SAMLDebugLog($msg);
            }
        } else {
            $msg = 'Invalid SAML response: Single Sign-On IDP certificate does not match certificate supplied in SAML request.';
            echo $msg;
            SAMLDebugLog($msg);
        }
    } catch (Exception $e) {
        $msg = 'Invalid SAML response: ' . Scrub::text($e->getMessage());
        error(ErrorMapper::ERROR_PROCESSING, $msg);
        SAMLDebugLog($msg);
    }
}
else {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}