<?php
/**
 * SAMPLE Code to demonstrate how to handle a SAML assertion response.
 *
 * The URL of this file will have been given during the SAML authorization.
 * After a successful authorization, the browser will be directed to this
 * link where it will send a certified response via $_POST.
 */

error_reporting(E_ALL);

$settings = NULL;
require 'settings.php';

//print_r($_POST);
//var_dump(base64_decode($_POST['SAMLResponse']));
$samlResponse = new OneLogin_Saml_Response($settings, $_POST['SAMLResponse']);

try {
    if ($samlResponse->isValid()) {
        $sessionHandler = new Session();
        if ($sessionHandler->create($samlResponse->getNameId())) {
            $page = '/';
            if (isset($_GET['page']) && preg_match('|^/|', $_GET['page'] && substr($_GET['page'],0,6) != '/login')) {
                $page = $_GET['page'];
            }
            SitePage::localRedir($page);
        }
        else {
            echo 'Invalid SAML user.';
        }
    }
    else {
        echo 'Invalid SAML response.';
    }
}
catch (Exception $e) {
    echo 'Invalid SAML response: ' . $e->getMessage();
//var_dump($samlResponse);
}
