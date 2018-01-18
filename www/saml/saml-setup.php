<?php

use BrowscapPHP\Browscap;
require_once __DIR__.'/../../library/Bootstrap.php';


$spBaseUrl = 'https://'.Config::get('site_hostname').'.'.Config::get('site_domain');

$settingsInfo = array (
    'sp' => [
        'entityId' => $spBaseUrl.'/saml/metadata.php',
        'assertionConsumerService' => [
            'url' => $spBaseUrl.'/saml/index.php',
        ],
        'singleLogoutService' => [
            'url' => $spBaseUrl.'/saml/index.php?sls',
        ],
        'NameIDFormat' => OneLogin_Saml2_Constants::NAMEID_TRANSIENT,
        'x509cert' => Cfg::get('saml_public_cert'),
        'privateKey' => Cfg::get('saml_private_cert'),
    ],
    'idp' => [
        'entityId' => Config::get('saml_sso_url') ?: Cfg::get('saml_idp_metadata_url'),
        'singleSignOnService' => array (
            'url' => Config::get('saml_sso_url'),
            'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_REDIRECT,
        ),
        // SLO endpoint info of the IdP.
        'singleLogoutService' => array (
            'url' => Config::get('saml_slo_url'),
            'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_REDIRECT,
        ),
        'x509cert' => preg_replace("/\r/m", "", Config::get('saml_idp_certificate')),
    ],
    'security' => [
        'requestedAuthnContext'             => useSAMLKerberos() ? OneLogin_Saml2_Constants::AC_WINDOWS : OneLogin_Saml2_Constants::AC_PASSWORD_PROTECTED,
        'requestedAuthnContextComparison'   => Config::get('saml_auth_comparison'),
    ]
);


try {
    $url = new \Zend\Uri\Uri(Cfg::get('saml_idp_metadata_url'));
    $idpMetadataUrl = $url->toString();

    $cache = new Alaress_Saml_Cache();
    $idpMetadata_raw = $cache->init($idpMetadataUrl)->getStreamContents();

    try {
        $settingsInfo = OneLogin_Saml2_IdPMetadataParser::injectIntoSettings($settingsInfo, OneLogin_Saml2_IdPMetadataParser::parseXML($idpMetadata_raw));
    } catch (Exception $e) {
        $cache->invalidateCache();
        SAMLDebugLog("Invalidating cache as we failed to get valid metadata from IDP");
    }

} catch (Exception $e) {
    //noop, metadata url invalid
}


/**
 * Checks if the user is in the correct IP range (by the whitelist)
 * and also of a supported browser / platform that supports Kerberos Auth
 *
 * @return boolean
 */
function useSAMLKerberos() {

    // If whitelist not in use, don't use kerberos at all
    if (trim(Config::get('saml_krb_whitelist'))==''){
        SAMLDebugLog("Kerberos whitelist not in use");
        return false;
    }

    // Check if the user is in the IP whitelist


    if (!ipInCIDRList(Session::getIP(), Config::get('saml_krb_whitelist'))) {
        SAMLDebugLog("IP not in kerberos whitelist");
        return false;
    }
    unset($tempsession);

    /**
     * Check if the users platform/browser supports kerberos
     *
     * Acceptance matrix:
     * via: (http://en.wikipedia.org/wiki/Integrated_Windows_Authentication#Supported_web_browsers)
     *  - Must be Desktop/Laptop
     *  - IE on Windows
     *  - Chrome on Windows and MacOSX
     *  - Safari on MacOSX
     *  - Firefox on Windows (if enabled in the Firefox install)
     *
     * Errs on the side of kerberos than non-kerberos
     */
    SAMLDebugLog("IP in kerberos whitelist, attempting browser/platform check");
//    $bc = new Browscap(__DIR__.'/../../tools/browscap');
    $bc = new Browscap();
    // Don't attempt to update
    $bc->doAutoUpdate = false;
    // Use local version of the browscap.ini file
    $bc->updateMethod = 'local';
    // really ensure we don't attemp to update the browscap.ini file (1 year)
    $bc->updateInterval = '31536000';

    $current_browser = $bc->getBrowser();
    SAMLDebugLog(json_encode($current_browser));
    if ($current_browser->Device_Type == 'Desktop' || $current_browser->Device_Type == 'Tablet') {
        if (substr($current_browser->Platform,0,3)=='Win' || $current_browser->Platform == 'MacOSX') {
            switch($current_browser->Browser) {
                case 'IE':
                    return true;

                case 'Edge':
                    return true;

                case 'Chrome':
                    if (Config::get('saml_krb_chrome')) { return true; } else { return false; }

                case 'Safari':
                    if (Config::get('saml_krb_safari')) { return true; } else { return false; }

                case 'Firefox':
                    if (Config::get('saml_krb_firefox')) { return true; } else { return false; }

                default:
                    return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function SAMLDebugLog($msg) {
    if (Config::get('saml_debug')) {
        file_put_contents(Config::get('logs_dir')."/saml.log", "[".date("Y-m-d H:i:s")."] ".$_SERVER['REMOTE_ADDR']." ".$msg."\n", FILE_APPEND);
    }
}

/**
 * Checks an if an IP address is in a CIDR range or matches an individual address
 *
 * @return boolean
 */
function ipInCIDR ($ip, $cidr) {
    $cidr = trim($cidr);

    // Allow checking of individual IP address, not just CIDR ranges
    if ($ip == $cidr) {
        return true;
    }

    list ($network, $mask) = array_map('trim', explode ("/", $cidr));

    // Fail out invalid addresses
    if (strlen($network) == 0 || is_null($mask)) {
        return false;
    }

    $ip_net = ip2long ($network);
    $ip_mask = ~((1 << (32 - $mask)) - 1);

    $ip_ip = ip2long ($ip);
    $ip_ip_net = $ip_ip & $ip_mask;

    $result = ($ip_ip_net == $ip_net);

    return $result;
}

/**
 * Checks an IP address against a comma separated list of IPs/CIDR ranges
 *
 * @return boolean
 */
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

