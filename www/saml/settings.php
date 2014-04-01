<?php
/**
 * SAMPLE Code to demonstrate how provide SAML settings.
 *
 * The settings are contained within a OneLogin_Saml_Settings object. You need to
 * provide, at a minimum, the following things:
 *
 *  - idpSingleSignOnUrl
 *    This is the URL to forward to for auth requests.
 *    It will be provided by your IdP.
 *
 *  - idpPublicCertificate
 *    This is a certificate required to authenticate your request.
 *    This certificate should be provided by your IdP.
 * 
 *  - spReturnUrl
 *    The URL that the IdP should redirect to once the authorization is complete.
 *    You must provide this, and it should point to the consume.php script or its equivalent.
 */

require_once __DIR__.'/../../include/Bootstrap.php';

$settings = new OneLogin_Saml_Settings();

// When using Service Provider Initiated SSO (starting at index.php), this URL asks the IdP to authenticate the user.
$settings->idpSingleSignOnUrl = Config::get('saml_sso_url');

// The certificate for the users account in the IdP
$settings->idpPublicCertificate = preg_replace("/\r/m", "", Config::get('saml_idp_certificate'));

// The URL where to the SAML Response/SAML Assertion will be posted
$settings->spReturnUrl = 'https://'.$_SERVER['HTTP_HOST'].'/saml/consume.php';

// Name of this application
$settings->spIssuer = 'schoolbox';

// Tells the IdP to return the email address of the current user
#$settings->requestedNameIdFormat = OneLogin_Saml_Settings::NAMEID_EMAIL_ADDRESS;
$settings->requestedNameIdFormat = OneLogin_Saml_Settings::NAMEID_TRANSIENT;

function ipInCIDR ($ip, $cidr) {
    $cidr = trim($cidr);

    // Allow checking of individual IP address, not just CIDR ranges
    if ($ip == $cidr) {
        var_dump(true);
        return true;
    }

    list ($network, $mask) = array_map('trim', explode ("/", $cidr));

    // Fail out invalid addresses
    if (strlen($network) == 0 || is_null($mask)) {
        var_dump(false);
        return false;
    }

    $ip_net = ip2long ($network);
    $ip_mask = ~((1 << (32 - $mask)) - 1);

    $ip_ip = ip2long ($ip);
    $ip_ip_net = $ip_ip & $ip_mask;

    $result = ($ip_ip_net == $ip_net);

    return $result;
}
  
function ipInCIDRList ($ip, $cidr_list) {
    $cidr_array = array();

    if (is_string($cidr_list) && strlen(trim($cidr_list))>0) {
        $cidr_array = array_map('trim', explode(',',$cidr_list));
    } else if (is_array($cidr_list)) {
        $cidr_array = array_map('trim', $cidr_list);
    } else {
        unset($ip,$cidr_list,$cidr_array);
        return false;
    }

    $returnval = false;
    foreach ($cidr_array as $cidr) {
        if (ipInCIDR($ip, $cidr)) {
            $returnval = true;
        }
    }
    unset($ip,$cidr_list,$cidr_array);

    return $returnval;
}

// TODO: replace $_SERVER['REMOTE_ADDR'] with one of the prebuilt GetIP() functions
$settings->useKerberos = ipInCIDRList($_SERVER['REMOTE_ADDR'], Config::get('saml_krb_whitelist'));

return $settings;
