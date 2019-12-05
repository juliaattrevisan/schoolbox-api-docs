<?php
/**
 * SAMPLE Code to demonstrate how to handle a SAML assertion response.
 *
 * The URL of this file will have been given during the SAML authorization.
 * After a successful authorization, the browser will be directed to this
 * link where it will send a certified response via $_POST.
 */

use Schoolbox\Error\Mapper as ErrorMapper;
require 'saml-setup.php';

$page = new SitePage(false);
$auth = new OneLogin_Saml2_Auth($settingsInfo);


/* Handle Single Logout commands */
if (isset($_GET['sls'])) {
    if (isset($_GET['SAMLRequest'])) {
        SAMLDebugLog('SLS Request:'.base64_decode($_GET['SAMLRequest']));
    }
    try {
        SAMLDebugLog("Attempting SLS");
        $auth->processSLO(false, null, true);
    } catch (OneLogin_Saml2_Error $e) {
        SAMLDebugLog("SLS Error: ".$e->getMessage());
        error(ErrorMapper::ERROR_PROCESSING, $e->getMessage());
    }

    $errors = $auth->getErrors();
    if (!empty($errors)) {
        $errorReason = $auth->getLastErrorReason() ?? end($errors);
        SAMLDebugLog("SLS Error(s): ".json_encode($errors));
        SAMLDebugLog("SLS Error: ".$errorReason);
        error(ErrorMapper::ERROR_PROCESSING, $errorReason);
    }

    $samlLogoutUrl = trim(Config::get('saml_logout_url'));

    // Replace some dynamic variables in the logout URL if set
    $samlLogoutUrl = str_replace('{SCHOOLBOX_ADDRESS}',$_SERVER['SERVER_NAME'],$samlLogoutUrl);

    if (strlen($samlLogoutUrl) > 0) {
        // Redirect to logout location
        header('Location: ' . $samlLogoutUrl);
        exit;
    }
    else {
        SitePage::localRedir("/login/");
    }
}

else if(isset($_POST['SAMLResponse'])) {
    SAMLDebugLog(base64_decode($_POST['SAMLResponse']));

    if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
        $requestID = $_SESSION['AuthNRequestID'];
    } else {
        $requestID = null;
    }

    try {
        $auth->processResponse($requestID);
    } catch (OneLogin_Saml2_Error $e) {
        error(ErrorMapper::ERROR_PROCESSING, $e->getMessage());
    }

    $errors = $auth->getErrors();
    if (!empty($errors)) {
        error(ErrorMapper::ERROR_PROCESSING, implode("\n", $errors));
    }

    $sessionHandler = new Session();
    if ($sessionHandler->create($auth->getNameId())) {
        $page = '/';
        if (isset($_POST['RelayState']) && preg_match('|^/|', $_POST['RelayState']) && substr($_POST['RelayState'], 0, 6) != '/login') {
            $page = $_POST['RelayState'];
        }

        $_SESSION['samlUserdata'] = $auth->getAttributes();
        $_SESSION['samlNameId'] = $auth->getNameId();
        $_SESSION['samlNameIdFormat'] = $auth->getNameIdFormat();
        $_SESSION['samlSessionIndex'] = $auth->getSessionIndex();
        unset($_SESSION['AuthNRequestID']);

        SAMLDebugLog("SAML auth successful");
        SitePage::localRedir($page);

    } else {
        $msg = 'Invalid SAML user.';
        $errorTracker[] = $msg;
        SAMLDebugLog($msg);
        error(ErrorMapper::ERROR_USER_NOT_FOUND);
    }
}
else {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}
