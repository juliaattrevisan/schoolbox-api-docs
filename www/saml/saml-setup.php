<?php

use BrowscapPHP\Browscap;
use Psr\Log\NullLogger;
use Roave\DoctrineSimpleCache\SimpleCacheAdapter;
use Symfony\Component\HttpFoundation\IpUtils;
use Alaress\Saml\CacheReader;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$spBaseUrl = 'https://'.Config::get('site_hostname').'.'.Config::get('site_domain');


$settingsInfo = array (
    'idp' => [
        'entityId' => Config::get('saml_sso_url') ?: Config::get('saml_idp_metadata_url'),
        'singleSignOnService' => array (
            'url' => Config::get('saml_sso_url'),
        ),
        // SLO endpoint info of the IdP.
        'singleLogoutService' => array (
            'url' => Config::get('saml_slo_url'),
        ),
        'x509cert' => preg_replace("/\r/m", "", Config::get('saml_idp_certificate')),
    ],
    'security' => [
        'requestedAuthnContext'             => false,
        'signatureAlgorithm'                => XMLSecurityKey::RSA_SHA256,
        'digestAlgorithm'                   => XMLSecurityDSig::SHA256,
        'lowercaseUrlencoding'              => true,
        'wantNameId'                        => true,
        'logoutRequestSigned'               => Config::get('saml_private_cert') ? true : false,
        'logoutResponseSigned'              => Config::get('saml_private_cert') ? true : false,

    ],
);

// requestedAuthnContext
if (Config::get('saml_auth_context') !== 'unspecified') {
    $settingsInfo['security']['requestedAuthnContext'] = [useSAMLKerberos() ? OneLogin_Saml2_Constants::AC_WINDOWS : OneLogin_Saml2_Constants::AC_PASSWORD_PROTECTED];
    $settingsInfo['security']['requestedAuthnContextComparison'] = Config::get('saml_auth_comparison');
}

SAMLDebugLog(json_encode($settingsInfo));

try {
    $url = new \Zend\Uri\Uri(Config::get('saml_idp_metadata_url'));
    $idpMetadataUrl = $url->toString();

    $cache = new Alaress_Saml_Cache();
    $idpMetadata = $cache->init($idpMetadataUrl);

    // if cache file exists but empty, try invalidating it.
    if (file_exists($idpMetadata->getCachedFile()) && empty($idpMetadata->getStreamContents())) {
        $idpMetadata->invalidateCache();
    }

    // if cache exists, update $setting info from cache
    if (file_exists($idpMetadata->getCachedFile())) {
        try {
            $settingsInfo = OneLogin_Saml2_IdPMetadataParser::injectIntoSettings($settingsInfo, OneLogin_Saml2_IdPMetadataParser::parseXML($idpMetadata->getStreamContents()));
        } catch (Exception $e) {
            $cache->invalidateCache();
            SAMLDebugLog("Invalidating cache as we failed to get valid metadata from IDP");
        }
    }
} catch (Exception $e) {
    //noop, metadata url invalid
}

/**
 * Exports the private key for use as it is stored passphrase encoded
 */
$privateKey = false;

$privateKeyResource = openssl_pkey_get_private(Config::get('saml_private_cert'), sha1(strtolower(trim(Config::get('site_school_code')))));
if( $privateKeyResource !== false ) {
    openssl_pkey_export($privateKeyResource, $privateKey);
}

$settingsInfo['sp'] = [
        'entityId' => $spBaseUrl,
        'assertionConsumerService' => [
            'url' => $spBaseUrl.'/saml/consume.php',
            'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_POST,

        ],
        'singleLogoutService' => [
            'url' => $spBaseUrl.'/saml/consume.php?sls',
            'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_REDIRECT,
        ],
        'NameIDFormat' => OneLogin_Saml2_Constants::NAMEID_UNSPECIFIED,
        'x509cert' => Config::get('saml_public_cert'),
        'privateKey' => $privateKey ? $privateKey : Config::get('saml_public_cert'),
    ];

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


    if (!IpUtils::checkIp(Session::getIP(),  array_map('trim', explode(',',Config::get('saml_krb_whitelist'))))) {
        SAMLDebugLog("IP not in kerberos whitelist");
        return false;
    }

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

    $bc = new Browscap(
        new SimpleCacheAdapter(
            new CacheReader(__DIR__ . '/../../tools/browscap')
        ),
        new NullLogger()
    );

    $current_browser = $bc->getBrowser();
    SAMLDebugLog(json_encode($current_browser));
    if ($current_browser->device_type == 'Desktop' || $current_browser->device_type == 'Tablet') {
        if (substr($current_browser->platform,0,3)=='Win' || $current_browser->platform == 'MacOSX') {
            switch($current_browser->browser) {
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
