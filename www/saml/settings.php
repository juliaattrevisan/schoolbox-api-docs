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
use \Alaress\Saml;

require_once __DIR__.'/../../library/Bootstrap.php';

$idp = idpMetadata();

$settings = new OneLogin_Saml_Settings();

// When using Service Provider Initiated SSO (starting at index.php), this URL asks the IdP to authenticate the user.
$settings->idpSingleSignOnUrl = Config::get('saml_sso_url');
if ($idp) {
    foreach ($idp['SingleSignOnService'] as $attribute) {
        if ($attribute['Binding'] == "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" && strlen(trim($attribute['Location'])) > 0) {
            $settings->idpSingleSignOnUrl = trim($attribute['Location']);
        }
    }
}

// The certificate for the users account in the IdP
$settings->idpPublicCertificate = preg_replace("/\r/m", "", Config::get('saml_idp_certificate'));

// The URL where to the SAML Response/SAML Assertion will be posted
$settings->spReturnUrl = 'https://'.Config::get('site_hostname').'.'.Config::get('site_domain').'/saml/consume.php';

// Name of this application
$settings->spIssuer = 'https://'.Config::get('site_hostname').'.'.Config::get('site_domain');

// Tells the IdP to return the email address of the current user
#$settings->requestedNameIdFormat = OneLogin_Saml_Settings::NAMEID_EMAIL_ADDRESS;
$settings->requestedNameIdFormat = OneLogin_Saml_Settings::NAMEID_TRANSIENT;

// Define the Authncontext comparison type
$settings->authncontextComparison = Config::get('saml_auth_comparison');

/**
* Retrieve metadata from the identity provider
*
* @return array
*/
function idpMetadata() {
    try {
        $url = new \Zend\Uri\Uri(Cfg::get('saml_idp_metadata_url'));
        $idpMetadataUrl = $url->toString();
    } catch (Exception $e) {
        /*
         * Unable to use URL provided, ignore IDP metadata
         */
        return false;
    }

    $cache = new Alaress_Saml_Cache();

    $idpMetadata_raw = $cache->init($idpMetadataUrl)
                                ->getStreamContents();

    $idpMetadata = simplexml_load_string($idpMetadata_raw);
    $idp['SigningKeys'] = array();
    $idp['SingleSignOnService'] = array();
    $idp['SingleLogoutService'] = array();
    if ($idpMetadata) {
        $x = 0;
        foreach ($idpMetadata->IDPSSODescriptor->KeyDescriptor as $key) {
            foreach ($key->attributes() as $keytype => $keytypevalue) {
                if ((string)$keytypevalue == "signing") {
                    $cert = "-----BEGIN CERTIFICATE-----\n";
                    $cert .= implode("\n",str_split((string)$key->KeyInfo->X509Data->X509Certificate, 64));
                    $cert .= "\n-----END CERTIFICATE-----";
                    $certData = openssl_x509_parse($cert);
                    if (is_array($certData)) {
                        $idp['SigningKeys'][$x]['X509Cert'] = $cert;
                        if (array_key_exists('validFrom', $certData)) {
                            $idp['SigningKeys'][$x]['Dates']['validFrom'] = (new \Alaress_Date_Format($certData['validFrom'],'ymdHise'))->getDatabase();
                        }
                        if (array_key_exists('validTo', $certData)) {
                            $idp['SigningKeys'][$x]['Dates']['validTo'] = (new \Alaress_Date_Format($certData['validTo'],'ymdHise'))->getDatabase();
                        }
                }
                $x++;
                }
            }
        }

        $x = 0;
        foreach ($idpMetadata->IDPSSODescriptor->SingleSignOnService as $SSO) {
        foreach ($SSO->attributes() as $key => $value) {
            $idp['SingleSignOnService'][$x][$key] = (string)$value;
        }
        $x++;
        }

        $x = 0;
        foreach ($idpMetadata->IDPSSODescriptor->SingleLogoutService as $SLO) {
            foreach ($SLO->attributes() as $key => $value) {
                $idp['SingleLogoutService'][$x][$key] = (string)$value;
            }
            $x++;
        }
    } else {
        return false;
    }
    return $idp;
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

/**
* Checks if the user is in the correct IP range (by the whitelist)
* and also of a supported browser / platform that supports Kerberos Auth
*
* @return boolean
*/
use BrowscapPHP\Browscap;
function useSAMLKerberos() {

    // If whitelist not in use, don't use kerberos at all
    if (trim(Config::get('saml_krb_whitelist'))==''){
        SAMLDebugLog("Kerberos whitelist not in use");
        return false;
    }

    // Check if the user is in the IP whitelist
    $tempsession = new Session;
    if (!ipInCIDRList($tempsession->getIP(), Config::get('saml_krb_whitelist'))) {
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

$settings->useKerberos = useSAMLKerberos();
SAMLDebugLog(json_encode("Attempt to use kerberos? ".json_encode($settings->useKerberos)));

return $settings;
